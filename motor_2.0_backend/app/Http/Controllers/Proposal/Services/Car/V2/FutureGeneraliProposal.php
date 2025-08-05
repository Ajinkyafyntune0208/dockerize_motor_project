<?php

namespace App\Http\Controllers\Proposal\Services\Car\V2;

use App\Http\Controllers\SyncPremiumDetail\Car\FutureGeneraliPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterPremiumType;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\UserProductJourney;
use App\Models\FgCarAddonConfiguration;
use App\Models\MasterPolicy;
use App\Models\WebServiceRequestResponse;


include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class futureGeneraliProposal
{
    public static function applicable_addons($age, $with_zd, $identifier)
    {
        // $ZCETR = $ZDCET = $ZDCNT = $STNCB = $ZDCNE = $ZDCNS = $STZDP = $STRSA = $RSPBK = 'N';
        $applicable_addons = [];
        $error_message = [];
      
        if ($identifier == 'ZCETR' && $with_zd) {
            if ($age <= 3) {
                array_push($applicable_addons, ['CoverCode' => 'ZCETR']);
                // array_push($applicable_addons, ['CoverCode' => 'STNCB']);
                return $applicable_addons;
            } 
            // else {
            //     array_push($error_message, [
            //         'status' => false,
            //         'message' => 'car age is greater than 3'
            //     ]);
            //     return $error_message;
            // }
        }

        if (in_array($identifier,['ZDCNT', 'ZDCET']) && $with_zd) {
            if ($age <= 5) {
                // $data=['cover_code'=>'ZDCNT'];
                array_push($applicable_addons, ['CoverCode' => $identifier]);
                // array_push($applicable_addons, ['CoverCode' => 'STNCB']);
                return $applicable_addons;
            } 
            // else {
            //     array_push($error_message, [
            //         'status' => false,
            //         'message' => 'car age is greater than 5'
            //     ]);
            //     return $error_message;
            // }
        }
        if (in_array($identifier,['STNCB', 'STINC'])) {
            if ($age <= 5) {
                array_push($applicable_addons, ['CoverCode' => $identifier]);
                return $applicable_addons;
            } 
            // else {
            //     array_push($error_message, [
            //         'status' => false,
            //         'message' => 'car age is greater than 5'
            //     ]);
            //     return $error_message;
            // }
        }
        if (in_array($identifier,['ZDCNE', 'ZDCNS']) && $with_zd) {
            if ($age <= 7) {
                array_push($applicable_addons, ['CoverCode' => $identifier]);
                return $applicable_addons;
            } 
            // else {
            //     array_push($error_message, [
            //         'status' => false,
            //         'message' => 'car age is greater than 7'
            //     ]);
            //     return $error_message;
            // }
        }
        if (in_array($identifier,['STZDP', 'STRSA'])) {
            if ($age <= 7) {
                array_push($applicable_addons, ['CoverCode' => $identifier]);
                array_push($applicable_addons, ['CoverCode' => 'STNCB']);
                return $applicable_addons;
            } 
            // else {
            //     array_push($error_message, [
            //         'status' => false,
            //         'message' => 'car age is greater than 7',
            //         'zerodep' => false,

            //     ]);
            //     return $error_message;
            // }
        }
        if ($identifier == 'basic'); {
            return $applicable_addons = [];
        }
        // if($age > 7)
        // {

        //     $age_range = '8';
        //    return $applicable_addons = [];
        // }

    }
    public static function get_applicable_addons($with_zd,$identifier)
    {
        $ZCETR = $ZDCET = $ZDCNT = $STNCB = $ZDCNE = $ZDCNS = $STZDP = $STRSA = $RSPBK = 'N';
            $applicable_addons = [];
            $error_message = [];
          
            if($identifier == 'ZCETR' && $with_zd)
            {
                    array_push($applicable_addons,['CoverCode' => 'ZCETR']);
                return $applicable_addons;  
            } 
            
            if(in_array($identifier,['ZDCNT', 'ZDCET']) && $with_zd)
            {
                    array_push($applicable_addons,['CoverCode' => $identifier]);
                    return $applicable_addons;
            }
            if(in_array($identifier,['STNCB', 'STINC']))
            {
                
                    array_push($applicable_addons,['CoverCode' => $identifier]);
                    return $applicable_addons;
            }
            if(in_array($identifier,['ZDCNE', 'ZDCNS']) && $with_zd)
            {
                    array_push($applicable_addons,['CoverCode' => $identifier]);
                    return $applicable_addons;
            }
            if(in_array($identifier,['STZDP', 'STRSA']))
            {
                    array_push($applicable_addons,['CoverCode' => $identifier]);
                    array_push($applicable_addons,['CoverCode' => 'STNCB']);
                    return $applicable_addons;
            }
            if($identifier == 'basic');
            {
                return $applicable_addons = [];
            }    
    }
    public static function get_fg_applicable_addons($age, $with_zd)
    {
        $ZCETR = $ZDCET = $ZDCNT = $STNCB = $ZDCNE = $ZDCNS = $STZDP = $STRSA = $RSPBK = 'N';
        $applicable_addons = [];
        if ($age <= 3) {
            $age_range = '3';
        } elseif ($age > 3 && $age <= 5) {
            $age_range = '5';
        } elseif ($age > 5 && $age <= 7) {
            $age_range = '7';
        } elseif ($age > 7) {
            $with_zd = 'N';
            $age_range = '8';
        }

        $addon_data = FgCarAddonConfiguration::where('age', '>=', $age_range)
            ->where('with_zd', $with_zd)
            ->get()
            ->toArray();
        foreach ($addon_data as $key => $value) {
            if ($value['cover_code'] == 'ZCETR') {
                $ZCETR = 'Y';
            } elseif ($value['cover_code'] == 'ZDCET') {
                $ZDCET = 'Y';
            } elseif ($value['cover_code'] == 'ZDCNT') {
                $ZDCNT = 'Y';
            } elseif ($value['cover_code'] == 'STNCB') {
                $STNCB = 'Y';
            } elseif ($value['cover_code'] == 'ZDCNE') {
                $ZDCNE = 'Y';
            } elseif ($value['cover_code'] == 'ZDCNS') {
                $ZDCNS = 'Y';
            } elseif ($value['cover_code'] == 'STZDP') {
                $STZDP = 'Y';
            } elseif ($value['cover_code'] == 'STRSA') {
                $STRSA = 'Y';
            } elseif ($value['cover_code'] == 'RSPBK') {
                $RSPBK = 'Y';
            }
        }
        $applicable_addons =
            [
                'ZCETR' => $ZCETR,
                'ZDCET' => $ZDCET,
                'ZDCNT' => $ZDCNT,
                'STNCB' => $STNCB,
                'ZDCNE' => $ZDCNE,
                'ZDCNS' => $ZDCNS,
                'STZDP' => $STZDP,
                'STRSA' => $STRSA,
                'RSPBK' => $RSPBK,
                'addons_description' => $addon_data
            ];
        return $applicable_addons;
    }
    public static function submit($proposal, $request)
    {
        $proposal->gender = (strtolower($proposal->gender) == "male" || $proposal->gender == "M") ? "M" : "F";
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 30,
            'address_2_limit'   => 30,
            'address_3_limit'   => 30,
            'address_4_limit'   => 30
        ];
        $getAddress = getAddress($address_data);
        $vehicle_block_data = DB::table('vehicle_block_data')
            ->where('registration_no', str_replace("-", "", $proposal->vehicale_registration_number))
            ->where('status', 'Active')
            ->select('ic_identifier')
            ->get()
            ->toArray();
        if (isset($vehicle_block_data[0])) {
            $block_bool = false;
            $block_array = explode(',', $vehicle_block_data[0]->ic_identifier);
            if (in_array('ALL', $block_array)) {
                $block_bool = true;
            } else if (in_array($request['companyAlias'], $block_array)) {
                $block_bool = true;
            }
            if ($block_bool == true) {
                return  [
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => $proposal->vehicale_registration_number . " Vehicle Number is Declined",
                    'request'           => [
                        'message'           => $proposal->vehicale_registration_number . " Vehicle Number is Declined",
                    ]
                ];
            }
        }
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        // dd($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        if ($requestData->ownership_changed == 'Y') {
            if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $premium_type_id = null;
                if (in_array($productData->premium_type_id, [1, 4])) {
                    $premium_type_id = 4;
                } else if (in_array($productData->premium_type_id, [3, 6])) {
                    $premium_type_id = 6;
                }

                $MasterPolicy = MasterPolicy::where('product_sub_type_id', 1)
                    ->where('insurance_company_id', 28)
                    ->where('premium_type_id', $premium_type_id)
                    ->where('status', 'Active')
                    ->get()
                    ->first();
                if ($MasterPolicy == false) {
                    return [
                        'premium_amount'    => 0,
                        'status'            => false,
                        'message'           => 'Breakin Product is Required Enable For OwnershipChange Inspection',
                        'request' => [
                            'message'       => 'Breakin Product is Required Enable For OwnershipChange Inspection',
                            'requestData'   => $requestData
                        ]
                    ];
                }
            }
        }
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $corporate_vehicle_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        $master_policy_id = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $additional_data = $proposal->additonal_data;
        $UserProductJourney = UserProductJourney::where('user_product_journey_id', $enquiryId)->first();
        $Quotation_no = UserProposal::select('additional_details_data')->where('user_product_journey_id', $proposal->user_product_journey_id)
            ->first();
        $data=json_decode($Quotation_no,true);
        $gt_quotation=json_decode($data['additional_details_data'],true);
        // dd($gt_quotation);

        $corporate_service = 'N';

        if (!empty($UserProductJourney->corporate_id) && !empty($UserProductJourney->domain_id) && config('IC.FUTURE_GENERALI.V2.CAR.IS_ENABLED_AFFINITY') == 'Y') {
            $corporate_service = 'Y';
        }

        $IsPos = 'N';
        $is_FG_pos_disabled = config('IC.FUTURE_GENERALI.V2.CAR.IS_POS_DISABLED');
        $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('IC.FUTURE_GENERALI.V2.CAR.IS_POS_ENABLED');
        $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('IC.FUTURE_GENERALI.V2.CAR.IS_POS_TESTING_MODE_ENABLE');
        //$is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
        $PanCardNo = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type', 'P')
            ->first();

        $mmv = get_mmv_details($productData, $requestData->version_id, 'future_generali');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $rto_code = $requestData->rto_code;
        $rto_code = RtoCodeWithOrWithoutZero($rto_code, true); //DL RTO code

        $rto_data = DB::table('future_generali_rto_master')
            ->where('rta_code', strtr($rto_code, ['-' => '']))
            ->first();

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $premium_type_code = $premium_type;
        $is_breakin = 'N';

        if ($premium_type == 'breakin') {
            $premium_type = 'comprehensive';
            $is_breakin = 'Y';
        }
        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
            $is_breakin = 'N';
        }
        if ($premium_type == 'own_damage_breakin') {
            $premium_type = 'own_damage';
            $is_breakin = 'Y';
        }

        if (!empty($requestData->previous_policy_expiry_date)) {
            $expiry_date = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $today_date_comparison = date('Y-m-d');
            if ($expiry_date >= new DateTime($today_date_comparison)) {
                $is_breakin = 'N';
            } else {
                if ($premium_type == 'third_party') {
                    $is_breakin = 'N';
                } else {
                    $is_breakin = 'Y';
                }
            }
        }
        $previous_policy_type = $requestData->previous_policy_type;
        if (in_array($previous_policy_type, ['Not sure', 'Third-party']) && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
            $is_breakin = 'Y';
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

        // car age calculation
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = $age / 12;
        $usedCar = 'N';

        $with_zd = ($productData->zero_dep == '0') ? 'Y' : 'N';
        // $fg_applicable_addons = self::get_fg_applicable_addons($car_age, $with_zd);
        if(config('APPLICABLE_ADDONS_WITH_AGE') == 'Y')
        {
            $fg_applicable_addons = self::applicable_addons($car_age, $with_zd, $productData->product_identifier);
        }
        else
        {
            $fg_applicable_addons = self::get_applicable_addons($with_zd, $productData->product_identifier);
        }
        foreach ($fg_applicable_addons as $data) {
            if (isset($data['status']) && $data['status'] == false) {
                return $data;
            }
        }
        // dd($fg_applicable_addons);
        $addon = [];
        $addon_req = 'N';

        if ($productData->zero_dep == '0') {

            //zero dep  7 year
            /*    if ($car_age <=7 && $productData->product_identifier=='zero_dep') {
                    $addon[] = [
                    'CoverCode' => 'STZDP',
                    ];
                }

                      //zero dep plus consumable 7 year
                      if ($car_age <=7 && $productData->product_identifier=='zero dep plus consumable') {
                      $addon[] = [
                      'CoverCode' => 'ZDCNS',
                      ];
                  }
                       //zero dep plus consumable plus engine 7 year
                       if ($car_age <=7 && $productData->product_identifier=='zero dep plus consumable plus engine') {
                       $addon[] = [
                          'CoverCode' => 'ZDCNE',
                      ];
                  }
                       //zero dep plus consumable plus tyre 5 year
                       if ($car_age <=5 && $productData->product_identifier=='zero dep plus consumable plus tyre') {
                       $addon[] = [
                          'CoverCode' => 'ZDCNT',
                      ];
                  }
                       //zero dep plus consumable plus engine plus tyre 5 year
                       if ($car_age <=5 && $productData->product_identifier=='zero dep plus consumable plus engine plus tyre') {
                       $addon[] = [
                          'CoverCode' => 'ZDCET',
                      ];
                  }

                       //zero dep plus consumable plus engine plus tyre plus RTI 3 year
                       if ($car_age <=3 && $productData->product_identifier=='zero dep plus consumable plus engine plus tyre plus RTI') {
                       $addon[] = [
                          'CoverCode' => 'ZCETR',
                      ];
                  }*/
            if ($productData->product_identifier == 'STZDP' /* && $fg_applicable_addons['STZDP'] == 'Y' */) {
                $addon[] = [
                    'CoverCode' => 'STZDP',
                ];
            } elseif ($productData->product_identifier == 'ZDCNS' /* && $fg_applicable_addons['ZDCNS'] == 'Y' */) {
                $addon[] = [
                    'CoverCode' => 'ZDCNS',
                ];
            } elseif ($productData->product_identifier == 'ZDCNE' /* && $fg_applicable_addons['ZDCNE'] == 'Y' */) {
                $addon[] = [
                    'CoverCode' => 'ZDCNE',
                ];
            } elseif ($productData->product_identifier == 'ZDCNT' /* && $fg_applicable_addons['ZDCNT'] == 'Y' */) {
                $addon[] = [
                    'CoverCode' => 'ZDCNT',
                ];
            } elseif ($productData->product_identifier == 'ZDCET' /* && $fg_applicable_addons['ZDCET'] == 'Y' */) {
                $addon[] = [
                    'CoverCode' => 'ZDCET',
                ];
            } elseif ($productData->product_identifier == 'ZCETR' /* && $fg_applicable_addons['ZCETR'] == 'Y' */) {
                $addon[] = [
                    'CoverCode' => 'ZCETR',
                ];
            }
        } else {
            //standlone rsa  7 year
            //rsa plus personal belonging plus key loss 7 year
            /*if ($car_age <=7 && $productData->product_identifier=='rsa plus personal belonging plus key loss') {
                $addon[] = [
                    'CoverCode' => 'RSPBK',
                ];
            }*/
            if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') {
                $addons = $selected_addons->applicable_addons;

                foreach ($addons as $value) {
                    if (in_array('Road Side Assistance', $value) &&  $productData->product_identifier == 'basic' /* && $fg_applicable_addons['STRSA'] == 'Y' */) {
                        $addon[] = [
                            'CoverCode' => 'STRSA',
                        ];
                    } elseif (in_array('Road Side Assistance', $value) && $productData->product_identifier == 'RSPBK' /* && $fg_applicable_addons['RSPBK'] == 'Y' */) {
                        $addon[] = [
                            'CoverCode' => 'RSPBK',
                        ];
                    }
                    if (in_array('NCB Protection', $value)) {
                        $addon[] = [
                            'CoverCode' => 'STNCB',
                        ];
                    }
                }
            }

            if ($productData->product_identifier == 'RSPBK' /* && $fg_applicable_addons['RSPBK'] == 'Y' */) // if product_identifier is selected as RSPBK then RSPBK should be passed in CoverCode regardless of 'Road Side Assistance' selected in selected_addons or not
            {
                if (!in_array('RSPBK', array_column($addon, 'CoverCode'))) {
                    $addon[] = [
                        'CoverCode' => 'RSPBK',
                    ];
                }
            }
        }
        /*if($productData->product_identifier == 'without_addon')
        {
            $addon = [];
        }*/
        if ($premium_type == 'third_party' /* || $car_age > 7*/) {
            $addon = [];
        }
        $today_date = date('d-m-Y h:i:s');

        if ($requestData->business_type == 'newbusiness') {
            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = 'N';
            $ncb_declaration = 'N';
            $NewCar = 'Y';
            $rollover = 'N';
            $policy_start_date = date('d/m/Y');
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $PolicyNo = $insurer  = $previous_insurer_name = $prev_ic_address1 = $prev_ic_address2 = $prev_ic_pincode = $PreviousPolExpDt = $prev_policy_number = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date = '';
            $contract_type = 'F13';
            $risk_type = 'F13';
            $reg_no = '';
            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
                if ($pos_data) {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    $contract_type = 'P13';
                    $risk_type = 'F13';
                }

                if ($pos_testing_mode === 'Y') {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    $contract_type = 'P13';
                    $risk_type = 'F13';
                }
                if (config('IC.FUTURE_GENERALI.V2.CAR.IS_NON_POS') == 'Y') {
                    $IsPos = 'N';
                    $PanCardNo  = '';
                    $contract_type = 'F13';
                    $risk_type = 'F13';
                }
            } elseif ($pos_testing_mode === 'Y' && $quote->idv <= 5000000) {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                $contract_type = 'P13';
                $risk_type = 'F13';
            }
        } else {
            if ($requestData->business_type == "breakin") {
                $policy_start_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($today_date)))));
            }
            if ($requestData->previous_policy_type == 'Not sure') {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
            }
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date)) / (60 * 60 * 24);

            if ($date_diff > 90) {
                $motor_expired_more_than_90_days = 'Y';
            } else {
                $motor_expired_more_than_90_days = 'N';
            }
            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = $requestData->is_claim;
            $ncb_declaration = 'N';
            $rollover = 'Y';
            $NewCar = 'N';

            $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';
            $registration_number = $reg_no;
            $registration_number = explode('-', $registration_number);

            if ($registration_number[0] == 'DL') {
                $registration_no = RtoCodeWithOrWithoutZero($registration_number[0] . $registration_number[1], true);
                $registration_number = $registration_no . '-' . $registration_number[2] . '-' . $registration_number[3];
            } else {
                $registration_number = $reg_no;
            }
            if ($claimMadeinPreviousPolicy == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party') {
                $ncb_declaration = 'Y';
                $motor_no_claim_bonus = $requestData->previous_ncb;
                $motor_applicable_ncb = $requestData->applicable_ncb;
            } else {
                $ncb_declaration = 'N';
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
            }

            if ($claimMadeinPreviousPolicy == 'Y' && $premium_type != 'third_party') {
                $motor_no_claim_bonus = $requestData->previous_ncb;
            }
            if ($requestData->previous_policy_type == 'Third-party') {
                $ncb_declaration = 'N';
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
            }
            if ($requestData->previous_policy_type != 'Not sure') {
                $previous_insure_name = DB::table('future_generali_prev_insurer')
                    ->where('insurer_id', $proposal->previous_insurance_company)->first();
                $previous_insurer_name = $previous_insure_name->name;
                $ClientCode = $proposal->previous_insurance_company;
                $PreviousPolExpDt = date('d/m/Y', strtotime($corporate_vehicle_quotes_request->previous_policy_expiry_date));
                $prev_policy_number = $proposal->previous_policy_number;

                $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
                $insurer = keysToLower($insurer);
                $prev_ic_address1 = $insurer->address_line_1;
                $prev_ic_address2 = $insurer->address_line_2;
                $prev_ic_pincode = $insurer->pin;
            }

            if($premium_type== "own_damage")
            {
                $contract_type = 'FVO';
                $risk_type = 'FVO';

            }
            else
            {
                $contract_type = 'FPV';
                $risk_type = 'FPV';
            }

            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && config('IC.FUTURE_GENERALI.V2.CAR.FUTURE_GENERALI_IS_NON_POS') != 'Y') {
                if ($pos_data) {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if ($premium_type == "own_damage") {
                        $contract_type = 'PVO';
                        $risk_type = 'FVO';
                    } else {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                    if ($requestData->business_type == 'newbusiness') {
                        $contract_type = 'P13';
                        $risk_type = 'F13';
                    }
                }

                if ($pos_testing_mode === 'Y') {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    if ($premium_type == "own_damage") {
                        $contract_type = 'PVO';
                        $risk_type = 'FVO';
                    } else {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                }
            } elseif ($pos_testing_mode === 'Y') {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                if ($premium_type == "own_damage") {
                    $contract_type = 'PVO';
                    $risk_type = 'FVO';
                } else {
                    $contract_type = 'PPV';
                    $risk_type = 'FPV';
                }
            }
            if ($is_pos_enabled == 'Y') {
                if ($pos_data) {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if ($premium_type == "own_damage") {
                        $contract_type = 'PVO';
                        $risk_type = 'FVO';
                    } else {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                    if ($requestData->business_type == 'newbusiness') {
                        $contract_type = 'P13';
                        $risk_type = 'F13';
                    }
                }
            }
            if (config('IC.FUTURE_GENERALI.V2.CAR.IS_POS_TESTING_MODE_ENABLE') == 'Y') {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                if ($requestData->business_type == 'newbusiness') {
                    $contract_type = 'P13';
                    $risk_type = 'F13';
                } else {
                    if ($premium_type == "own_damage") {
                        $contract_type = 'PVO';
                        $risk_type = 'FVO';
                    } else {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                }
            }
            $today_date = date('Y-m-d');
            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            $today_date = date('Y-m-d');
            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                if (in_array($premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            } else {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                if (in_array($premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            }

            if ($requestData->previous_policy_type == 'Not sure') {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                $usedCar = 'Y';
                $rollover = 'N';
                if (in_array($premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            }


            if ($requestData->ownership_changed == 'Y') {
                if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $is_breakin = 'N'; // as discussed with Nirmal sir and as per git #33724
                    $ncb_declaration = 'N';
                    $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                    $motor_no_claim_bonus = $requestData->previous_ncb = 0;
                    $motor_applicable_ncb = $requestData->applicable_ncb = 0;
                    $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                    $corporate_vehicle_quotes_request->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                    $PreviousPolExpDt = date('d/m/Y', strtotime($corporate_vehicle_quotes_request->previous_policy_expiry_date));
                }
            }
            if($requestData->business_type != 'newbusiness')
            {
            $policy_end_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
            }

            /*if($premium_type == 'own_damage')
            {
                $policy_start_date1 = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));

                $policy_end_date1 = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date1)));
                if(new DateTime(date('Y-m-d', strtotime($additional_data['prepolicy']['tpEndDate']))) < new DateTime($policy_end_date1))
                {
                    return
                    [
                        'status' => false,
                        'message' => 'TP Policy Expiry Date should be greater than or equal to OD policy expiry date'
                    ];
                }

            }*/
        }
        $uid = time() . rand(10000, 99999); //date('Ymshis').rand(0,9);
        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $salutation = 'MR';
            } else {
                $salutation = 'MS';
            }
        } else {
            $salutation = '';
        }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $bifuel = 'false';

        if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if ($value['name'] == 'Electrical Accessories') {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'Non-Electrical Accessories') {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $type_of_fuel = '5';
                    $bifuel = 'true';
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value['sumInsured'];
                }
            }
        }
        //PA for un named passenger
        $IsPAToUnnamedPassengerCovered = 'false';
        $PAToUnNamedPassenger_IsChecked = '';
        $PAToUnNamedPassenger_NoOfItems = '';
        $PAToUnNamedPassengerSI = 0;
        $IsLLPaidDriver = '0';

        if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = $selected_addons->additional_covers;
            foreach ($additional_covers as $value) {
                if ($value['name'] == 'Unnamed Passenger PA Cover') {
                    $IsPAToUnnamedPassengerCovered = 'true';
                    $PAToUnNamedPassenger_IsChecked = 'true';
                    $PAToUnNamedPassenger_NoOfItems = '1';
                    $PAToUnNamedPassengerSI = $value['sumInsured'];
                }
                if ($value['name'] == 'LL paid driver') {
                    $IsLLPaidDriver = '1';
                }
            }
        }
        $IsAntiTheftDiscount = 'false';
        if ($selected_addons && $selected_addons->discount != NULL && $selected_addons->discount != '') {
            $discount = $selected_addons->discount;
            foreach ($discount as $value) {
                if ($value['name'] == 'anti-theft device') {
                    $IsAntiTheftDiscount = 'true';
                }
            }
        }
        $cpa_selected = false;
        $cpa_year = '';
        if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
            $addons = $selected_addons->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_selected = true;
                    if ($requestData->business_type == 'newbusiness') {
                        $cpa_year = isset($value['tenure']) ? (string) $value['tenure'] : '1';
                    }
                }
            }
        }
        if ($requestData->vehicle_owner_type == 'I' && $cpa_selected == true && $premium_type != "own_damage") {
            $CPAReq = 'Y';
            $cpa_nom_name = $proposal->nominee_name;
            $cpa_nom_age = $proposal->nominee_age;
            $cpa_nom_age_det = 'Y';
            $cpa_nom_perc = '100';
            $cpa_relation = $proposal->nominee_relationship;
            $cpa_appointee_name = '';
            $cpa_appointe_rel = '';
             if ($requestData->business_type == 'newbusiness')
                {
                    $cpa_year = !empty($cpa_year) ? $cpa_year : 3;
                }
                else
                {
                    $cpa_year = !empty($cpa_year) ? $cpa_year : 1;
                } 
        } else {
            $CPAReq = 'N';
            $cpa_nom_name = '';
            $cpa_nom_age = '';
            $cpa_nom_age_det = '';
            $cpa_nom_perc = '';
            $cpa_relation = '';
            $cpa_appointee_name = '';
            $cpa_appointe_rel = '';
            $cpa_year = '';
        }
        if ($requestData->vehicle_owner_type == 'I'  && $premium_type != "own_damage") {
            if ($requestData->business_type == 'newbusiness')
            {
                $cpa_year = !empty($cpa_year) ? $cpa_year : 3;
            }
            else
            {
                $cpa_year = !empty($cpa_year) ? $cpa_year : 1;
            }   
        }
        $previous_tp_insurer_code = '';
        $cover_type = null;
        switch($premium_type)
        {
            case "comprehensive":
                $cover_type = "CO";
                break;
            case "own_damage":
                $cover_type = "OD";
                $previous_tp_insurer_code = DB::table('future_generali_previous_tp_insurer_master')
                    ->select('tp_insurer_code')
                                    ->where('client_code',$proposal->tp_insurance_company)->first()->tp_insurer_code;
                break;
            case "third_party":
                $cover_type = "LO";
                break;

        }
        if(($requestData->business_type == 'newbusiness' || $risk_type == 'F13')&& $premium_type == 'third_party'){
            $cover_type = "CO";
        }
        // chassis_number should be 17 digits
        if (!empty($proposal->chassis_number)) {
            $proposal->chassis_number = Str::padLeft($proposal->chassis_number, 17, '0'); // adding 0 to complete string length of 17//sprintf("%06s",$proposal->chassis_number);
        }
        if ($requestData->business_type != 'newbusiness' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
            //applicable addon for addon declaration logic
            $rsa = $engine_protector = $tyre_protection = $return_to_invoice = $Consumable = $personal_belonging = $key_and_lock_protect = $nilDepreciationCover = $ncb_protction = false;
            if ($selected_addons && !empty($selected_addons->applicable_addons)) {
                $addons = $selected_addons->applicable_addons;

                foreach ($addons as $value) {
                    if (in_array('Road Side Assistance', $value)) {
                        $rsa = true;
                    }
                    if (in_array('Engine Protector', $value)) {
                        $engine_protector = true;
                    }
                    if (in_array('Tyre Secure', $value)) {
                        $tyre_protection = true;
                    }
                    if (in_array('Return To Invoice', $value)) {
                        $return_to_invoice = true;
                    }
                    if (in_array('Consumable', $value)) {
                        $Consumable = true;
                    }
                    if (in_array('Loss of Personal Belongings', $value)) {
                        $personal_belonging = true;
                    }
                    if (in_array('Key Replacement', $value)) {
                        $key_and_lock_protect = true;
                    }
                    if (in_array('Zero Depreciation', $value)) {
                        $nilDepreciationCover = true;
                    }
                    if (in_array('NCB Protection', $value)) {
                        $ncb_protction = true;
                    }
                }
            }

            //checking last addons
            $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsConsumable_Cover = $PreviousPolicy_IsTyre_Cover = $PreviousPolicy_IsEngine_Cover = $PreviousPolicy_IsLpgCng_Cover  = $PreviousPolicy_IsRsa_Cover = $PreviousPolicy_IskeyReplace_Cover = $PreviousPolicy_IsncbProtection_Cover = $PreviousPolicy_IsreturnToInvoice_Cover = $PreviousPolicy_Islopb_Cover = $PreviousPolicy_electricleKit_Cover = $PreviousPolicy_nonElectricleKit_Cover = false;
            if (!empty($proposal->previous_policy_addons_list)) {
                $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
                foreach ($previous_policy_addons_list as $key => $value) {
                    if ($key == 'zeroDepreciation' && $value) {
                        $PreviousPolicy_IsZeroDept_Cover = true;
                    } else if ($key == 'consumables' && $value) {
                        $PreviousPolicy_IsConsumable_Cover = true;
                    } else if ($key == 'tyreSecure' && $value) {
                        $PreviousPolicy_IsTyre_Cover = true;
                    } else if ($key == 'engineProtector' && $value) {
                        $PreviousPolicy_IsEngine_Cover = true;
                    } else if ($key == 'roadSideAssistance' && $value) {
                        $PreviousPolicy_IsRsa_Cover = true;
                    } else if ($key == 'keyReplace' && $value) {
                        $PreviousPolicy_IskeyReplace_Cover = true;
                    } else if ($key == 'ncbProtection' && $value) {
                        $PreviousPolicy_IsncbProtection_Cover = true;
                    } else if ($key == 'returnToInvoice' && $value) {
                        $PreviousPolicy_IsreturnToInvoice_Cover = true;
                    } else if ($key == 'lopb' && $value) {
                        $PreviousPolicy_Islopb_Cover = true;
                    } else if ($key == 'externalBiKit' && $value) {
                        $PreviousPolicy_IsLpgCng_Cover = true;
                    } else if ($key == 'electricleKit' && $value) {
                        $PreviousPolicy_electricleKit_Cover = true;
                    } else if ($key == 'nonElectricleKit' && $value) {
                        $PreviousPolicy_nonElectricleKit_Cover = true;
                    }
                }
            }
            #addon declaration logic start 
            if ($nilDepreciationCover && !$PreviousPolicy_IsZeroDept_Cover) {
                $is_breakin = 'Y';
            }
            if ($rsa && !$PreviousPolicy_IsRsa_Cover) {
                $is_breakin = 'Y';
            }
            if ($Consumable && !$PreviousPolicy_IsConsumable_Cover) {
                $is_breakin = 'Y';
            }
            if ($key_and_lock_protect && !$PreviousPolicy_IskeyReplace_Cover) {
                $is_breakin = 'Y';
            }
            if ($engine_protector && !$PreviousPolicy_IsEngine_Cover) {
                $is_breakin = 'Y';
            }
            if ($ncb_protction && !$PreviousPolicy_IsncbProtection_Cover) {
                $is_breakin = 'Y';
            }
            if ($tyre_protection && !$PreviousPolicy_IsTyre_Cover) {
                $is_breakin = 'Y';
            }
            if ($return_to_invoice && !$PreviousPolicy_IsreturnToInvoice_Cover) {
                $is_breakin = 'Y';
            }
            if ($personal_belonging && !$PreviousPolicy_Islopb_Cover) {
                $is_breakin = 'Y';
            }
            if ($bifuel == 'true' && !$PreviousPolicy_IsLpgCng_Cover) {
                $is_breakin = 'Y';
            }
            if ($IsElectricalItemFitted == 'true' && !$PreviousPolicy_electricleKit_Cover) {
                $is_breakin = 'Y';
            }
            if ($IsNonElectricalItemFitted == 'true' && !$PreviousPolicy_nonElectricleKit_Cover) {
                $is_breakin = 'Y';
            }
            //end addon declaration
        }

        if ($corporate_service !== 'Y') {
            $VendorCode = config('IC.FUTURE_GENERALI.V2.CAR.VENDOR_CODE');
            $VendorUserId = config('IC.FUTURE_GENERALI.V2.CAR.VENDOR_CODE');
            $AgentCode = config('IC.FUTURE_GENERALI.V2.CAR.AGENT_CODE');
            $BranchCode = ($IsPos == 'Y') ? config('IC.FUTURE_GENERALI.V2.CAR.BRANCH_CODE_FOR_POS') : config('IC.FUTURE_GENERALI.V2.CAR.BRANCH_CODE');
        } else {
            $VendorCode = config('IC.FUTURE_GENERALI.V2.CAR.VENDOR_CODE_CORPORATE');
            $VendorUserId = config('IC.FUTURE_GENERALI.V2.CAR.VENDOR_CODE_CORPORATE');
            $AgentCode = config('IC.FUTURE_GENERALI.V2.CAR.AGENT_CODE_CORPORATE');
            $BranchCode = ($IsPos == 'Y') ? config('IC.FUTURE_GENERALI.V2.CAR.BRANCH_CODE_CORPORATE_FOR_POS') : config('IC.FUTURE_GENERALI.V2.CAR.BRANCH_CODE_CORPORATE');
        }
        $fuelTypes = [
            "bi-fuel" => "B",
            "cng" => "C",
            "diesel" => "D",
            "electric battery" => "E",
            "electric" => "E",
            "battery(b)" => "E",
            "gas" => "G",
            "hybrid electric" => "H",
            "hybrid" => "H",
            "lpg" => "L",
            "petrol" => "P",
            "petrol(p)" => "P",
            "unleaded petrol" => "U",
        ];
        if (isset($fuelTypes[strtolower($mmv_data->fuel_code)])) {
            $fuelType = $fuelTypes[strtolower($mmv_data->fuel_code)];
        } else {
            return [
                'status'  => false,
                'message' => 'Invalid fuel type.',
                'request' => $mmv_data
            ];
        }
        // dd($proposal->is_vehicle_finance);
        $quote_array = [
            '@attributes'  => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            ],
            'Uid'          => $uid,
            'VendorCode'   => $VendorCode,
            'VendorUserId' =>  $VendorUserId,
            'PolicyHeader' => [
                'PolicyStartDate' => $policy_start_date,
                'PolicyEndDate'   => $policy_end_date,
                'AgentCode'       => $AgentCode,
                'BranchCode'      => $BranchCode,
                // 'strPolicyQuoteNumber' => $gt_quotation['quotation_no'],
                'MajorClass'      => 'MOT',
                'ContractType'    => $contract_type,
                'METHOD'          => 'ENQ',
                'PolicyIssueType' => 'I',
                'PolicyNo'        => '',
                'ClientID'        => '',
                'ReceiptNo'       => '',
            ],
            'POS_MISP'     => [
                'Type'  => ($IsPos == 'Y') ? 'P' : '',
                'PanNo' => ($IsPos == 'Y') ? $PanCardNo : '',
            ],
            'Client'       => [
                'ClientCategory'    => '',
                'ClientType'    => $requestData->vehicle_owner_type,
                'CreationType'  => 'C',
                'Salutation'    => $salutation,
                'FirstName'     => $proposal->first_name,
                'LastName'      => $proposal->last_name,
                'DOB'           => date('d/m/Y', strtotime($proposal->dob)),
                'Gender'        => $proposal->gender,
                'MaritalStatus' => $proposal->marital_status == 'Single' ? 'S' : ($proposal->marital_status == 'Married' ? 'M' : ''),
                'Occupation'    => $requestData->vehicle_owner_type == 'C' ? 'OTHR' : $proposal->occupation,
                'PANNo'         => isset($proposal->pan_number) ? $proposal->pan_number : '',
                'GSTIN'         => isset($proposal->gst_number) ? $proposal->gst_number : '',
                'AadharNo'      => '',
                'EIANo'         => '',
                'CKYCNo'        => $proposal->ckyc_number,
                'CKYCRefNo'     => $proposal->ckyc_reference_id,

                'Address1'      => [
                    'AddrLine1'   => trim($getAddress['address_1']),
                    'AddrLine2'   => trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : '..',
                    'AddrLine3'   => trim($getAddress['address_3']),
                    'Landmark'    => trim($getAddress['address_4']),
                    'Pincode'     => $proposal->pincode,
                    'City'        => $proposal->city,
                    'State'       => $proposal->state,
                    'Country'     => 'IND',
                    'AddressType' => 'R',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => $proposal->mobile_number,
                    'EmailAddr'   => $proposal->email,
                ],
                'Address2'      => [
                    'AddrLine1'   => '',
                    'AddrLine2'   => '',
                    'AddrLine3'   => '',
                    'Landmark'    => '',
                    'Pincode'     => '',
                    'City'        => '',
                    'State'       => '',
                    'Country'     => '',
                    'AddressType' => '',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => '',
                    'EmailAddr'   =>  '',
                ],
                'VIPFlag' => 'N',
                'VIPCategory' => ''
            ],
            'Receipt'      => [
                'UniqueTranKey'   => '',
                'CheckType'       => '',
                'BSBCode'         => '',
                'TransactionDate' => '',
                'ReceiptType'     => '',
                'Amount'          => '',
                'TCSAmount'       => '',
                'TranRefNo'       => '',
                'TranRefNoDate'   => '',
                'PGType'        => '',
            ],
            'Risk'         => [
                'RiskType'          => $risk_type,
                'Zone'              => $rto_data->zone,
                'Cover'             => $cover_type,
                'Vehicle'           => [
                    'TypeOfVehicle'           => '',
                    'VehicleClass'            => '',
                    'RTOCode'                 => str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code, true)),
                    'Make'                    => $mmv_data->make,
                    'ModelCode'               => $mmv_data->vehicle_code,
                    'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : str_replace('-', '', $registration_number),
                    'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'ManufacturingYear'       => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'FuelType'                => $fuelType,
                    'CNGOrLPG'                => [
                        'InbuiltKit'    =>  in_array($fuelType, ['C', 'L']) ? 'Y' : 'N',
                        'IVDOfCNGOrLPG' => $bifuel == 'true' ? $BiFuelKitSi : '',
                    ],
                    'BodyType'                => 'SOLO',
                    'EngineNo'                => isset($proposal->engine_number) ? $proposal->engine_number : '',
                    'ChassiNo'                => isset($proposal->chassis_number) ? $proposal->chassis_number: '',
                    'CubicCapacity'           => $mmv_data->cc,
                    'SeatingCapacity'         => $mmv_data->seating_capacity,
                    'IDV'                     => ceil($master_policy_id->idv),
                    'GrossWeigh'              => '',
                    'CarriageCapacityFlag'    => '',
                    'ValidPUC'                => 'Y',
                    'TrailerTowedBy'          => '',
                    'TrailerRegNo'            => '',
                    'NoOfTrailer'             => '',
                    'TrailerValLimPaxIDVDays' => '',
                    // 'TrailerValLimPaxIDVDays'  => '',
                    'TrailerChassisNo'         => '',
                    'TrailerMfgYear'            => '',
                    'SchoolBusFlag'         => '',
                    'BancaSegment'      => '',
                ],
                'InterestParty'     => [
                    'Code'     => $proposal->is_vehicle_finance == '1' ? 'HY' : '',
                    'BankName' => $proposal->is_vehicle_finance == '1' ? strtoupper($proposal->name_of_financer) : '',
                ],
                'AdditionalBenefit' => [
                    'Discount'                                 => '',
                    'ElectricalAccessoriesValues'              => $IsElectricalItemFitted == 'true' ? $ElectricalItemsTotalSI : '',
                    'NonElectricalAccessoriesValues'           => $IsNonElectricalItemFitted == 'true' ? $NonElectricalItemsTotalSI : '',
                    'FibreGlassTank'                           => '',
                    'GeographicalArea'                         => '',
                    'PACoverForUnnamedPassengers'              => $IsPAToUnnamedPassengerCovered == 'true' ? $PAToUnNamedPassengerSI : '',
                    'LegalLiabilitytoPaidDriver'               => $IsLLPaidDriver,
                    'LegalLiabilityForOtherEmployees'          => '',
                    'LegalLiabilityForNonFarePayingPassengers' => '',
                    'UseForHandicap'                           => '',
                    'AntiThiefDevice'                          => '',
                    'NCB'                                      => $motor_applicable_ncb,
                    'RestrictedTPPD'                           => '',
                    'PrivateCommercialUsage'                   => '',
                    'CPAYear' => $cpa_year,
                    'CPADisc'                                  =>'',
                    'IMT23'                                    => '',
                    'CPAReq'                                   => $CPAReq,
                    'CPA'                                      => [
                        'CPANomName'       => $cpa_nom_name,
                        'CPANomAge'        => $cpa_nom_age,
                        'CPANomAgeDet'     => $cpa_nom_age_det,
                        'CPANomPerc'       => $cpa_nom_perc,
                        'CPARelation'      => $cpa_relation,
                        'CPAAppointeeName' => $cpa_appointee_name,
                        'CPAAppointeRel'   => $cpa_appointe_rel
                    ],
                    'NPAReq'              => 'N',
                    'NPA'                 => [
                        'NPAName'         => '',
                        'NPALimit'        => '',
                        'NPANomName'      => '',
                        'NPANomAge'       => '',
                        'NPANomAgeDet'    => '',
                        'NPARel'          => '',
                        'NPAAppinteeName' => '',
                        'NPAAppinteeRel'  => '',
                    ],
                    'ExistingPACover'   => 'N',
                    'PA'    => [
                        'InsurerName'   => '',
                        'ExistingPAPolicyNo'    => '',
                        'ExistingPASumInsured'  => '',
                        'AlternatePolicyExpiryDate' => '',
                        'ValidLicense'      => ''
                    ],
                    'ZIMT34ID'  =>  '',
                ],
                'AddonReq'          => (config('IC.FUTURE_GENERALI.V2.CAR.IS_ADDON_ENABLE') == 'N' ? 'N' : (($premium_type == 'third_party' || $fg_applicable_addons == null) ? 'N' : 'Y')), // FOR ZERO DEP value is Y and COVER CODE is PLAN1
                'Addon'             => (((config('IC.FUTURE_GENERALI.V2.CAR.IS_ADDON_ENABLE') == 'N') || empty($fg_applicable_addons)) ? ['CoverCode' => ''] : $fg_applicable_addons),
                'PreviousTPInsDtls' => [
                    'PreviousInsurer' => ($premium_type == 'own_damage') ? $previous_tp_insurer_code : '',
                    'TPPolicyNumber' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_number : '',
                    'TPPolicyEffdate' => ($premium_type == 'own_damage') ? date('d/m/Y', strtotime($proposal->tp_start_date)) : '',
                    'TPPolicyExpiryDate' => ($premium_type == 'own_damage') ? date('d/m/Y', strtotime($proposal->tp_end_date)) : ''

                ],
                'PreviousInsDtls'   => [
                    'UsedCar'        => $usedCar,
                    'UsedCarList'    => [
                        'PurchaseDate'    => ($usedCar == 'Y') ? date('d/m/Y', strtotime($requestData->vehicle_register_date)) : '',
                        'InspectionRptNo' => '',
                        'InspectionDt'    => '',
                    ],
                    'RollOver'       => $rollover,
                    'RollOverList'   => [
                        'PolicyNo'              => ($rollover == 'N') ? '' :$prev_policy_number,
                        'InsuredName'           => ($rollover == 'N') ? '' :$previous_insurer_name,
                        'PreviousPolExpDt'      => ($rollover == 'N') ? '' :$PreviousPolExpDt,
                        'ClientCode'            => ($rollover == 'N') ? '' :$ClientCode,
                        'Address1'              => ($rollover == 'N') ? '' :$prev_ic_address1,
                        'Address2'              => ($rollover == 'N') ? '' :$prev_ic_address2,
                        'Address3'              => '',
                        'Address4'              => '',
                        'Address5'              => '',
                        'PinCode'               => ($rollover == 'N') ? '' :$prev_ic_pincode,
                        'InspectionRptNo'       => '',
                        'InspectionDt'          => '',
                        'NCBDeclartion'         => ($rollover == 'N') ? 'N' :$ncb_declaration,
                        'ClaimInExpiringPolicy' => ($rollover == 'N') ? 'N' :$claimMadeinPreviousPolicy,
                        'NCBInExpiringPolicy'   => ($rollover == 'N') ? 0 :$motor_no_claim_bonus,
                        'TypeOfDoc'             =>'',
                        'NoOfClaims'            =>''
                    ],
                    'NewVehicle'     => $NewCar,
                    'NewVehicleList' => [
                        'InspectionRptNo' => '',
                        'InspectionDt'    => '',
                    ],
                ],
                'ZLLOTFLG'     =>'',
                'GARAGE'        =>'',
                'ZREFRA'        =>'',
                'ZREFRB'        =>'',
                'ZIDVBODY'      =>'',
                'COVERNT'       =>'',
                'CNTISS'        =>'',
                'ZCVNTIME'      =>'',
                'AddressSeqNo'  =>'',

            ],
        ];

        $quote_array_CRT = [
            '@attributes'  => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            ],
            'Uid'          => $uid,
            'VendorCode'   => $VendorCode,
            'VendorUserId' =>  $VendorUserId,
            'PolicyHeader' => [
                'PolicyStartDate' => $policy_start_date,
                'PolicyEndDate'   => $policy_end_date,
                'AgentCode'       => $AgentCode,
                'BranchCode'      => $BranchCode,
                'strPolicyQuoteNumber' => '',
                'MajorClass'      => 'MOT',
                'ContractType'    => $contract_type,
                'METHOD'          => 'CRT',
                'PolicyIssueType' => 'I',
                'PolicyNo'        => '',
                'ClientID'        => '',
                'ReceiptNo'       => '',
            ],
            'POS_MISP'     => [
                'Type'  => ($IsPos == 'Y') ? 'P' : '',
                'PanNo' => ($IsPos == 'Y') ? $PanCardNo : '',
            ],
            'Client'       => [
                // 'ClientCategory'    => '',
                'ClientType'    => $requestData->vehicle_owner_type,
                'CreationType'  => 'C',
                'Salutation'    => $salutation,
                'FirstName'     => $proposal->first_name,
                'LastName'      => $proposal->last_name,
                'DOB'           => date('d/m/Y', strtotime($proposal->dob)),
                'Gender'        => $proposal->gender,
                'MaritalStatus' => $proposal->marital_status == 'Single' ? 'S' : ($proposal->marital_status == 'Married' ? 'M' : ''),
                'Occupation'    => $requestData->vehicle_owner_type == 'C' ? 'OTHR' : $proposal->occupation,
                'PANNo'         => isset($proposal->pan_number) ? $proposal->pan_number : '',
                'GSTIN'         => isset($proposal->gst_number) ? $proposal->gst_number : '',
                'AadharNo'      => '',
                'EIANo'         => '',
                'CKYCNo'        => $proposal->ckyc_number,
                'CKYCRefNo'     => $proposal->ckyc_reference_id,

                'Address1'      => [
                    'AddrLine1'   => trim($getAddress['address_1']),
                    'AddrLine2'   => trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : '..',
                    'AddrLine3'   => trim($getAddress['address_3']),
                    'Landmark'    => trim($getAddress['address_4']),
                    'Pincode'     => $proposal->pincode,
                    'City'        => $proposal->city,
                    'State'       => $proposal->state,
                    'Country'     => 'IND',
                    'AddressType' => 'R',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => $proposal->mobile_number,
                    'EmailAddr'   => $proposal->email,
                ],
                'Address2'      => [
                    'AddrLine1'   => '',
                    'AddrLine2'   => '',
                    'AddrLine3'   => '',
                    'Landmark'    => '',
                    'Pincode'     => '',
                    'City'        => '',
                    'State'       => '',
                    'Country'     => '',
                    'AddressType' => '',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => '',
                    'EmailAddr'   =>  '',
                ],
                // 'VIPFlag' => 'N',
                // 'VIPCategory' => ''
            ],
            'Receipt'      => [
                'UniqueTranKey'   => '',
                'CheckType'       => '',
                'BSBCode'         => '',
                'TransactionDate' => '',
                'ReceiptType'     => '',
                'Amount'          => '',
                'TCSAmount'       => '',
                'TranRefNo'       => '',
                'TranRefNoDate'   => '',
                // 'PGType'        => '',
            ],
            'Risk'         => [
                'RiskType'          => $risk_type,
                'Zone'              => $rto_data->zone,
                'Cover'             => $cover_type,
                'Vehicle'           => [
                    'TypeOfVehicle'           => '',
                    'VehicleClass'            => '',
                    'RTOCode'                 => str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code, true)),
                    'Make'                    => $mmv_data->make,
                    'ModelCode'               => $mmv_data->vehicle_code,
                    'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : str_replace('-', '', $registration_number),
                    'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'ManufacturingYear'       => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'FuelType'                => $fuelType,
                    'CNGOrLPG'                => [
                        'InbuiltKit'    =>  in_array($fuelType, ['C', 'L']) ? 'Y' : 'N',
                        'IVDOfCNGOrLPG' => $bifuel == 'true' ? $BiFuelKitSi : '',
                    ],
                    'BodyType'                => 'SOLO',
                    'EngineNo'                => isset($proposal->engine_number) ? $proposal->engine_number : '',
                    'ChassiNo'                => isset($proposal->chassis_number) ? $proposal->chassis_number: '',
                    'CubicCapacity'           => $mmv_data->cc,
                    'SeatingCapacity'         => $mmv_data->seating_capacity,
                    'IDV'                     => ceil($master_policy_id->idv),
                    'GrossWeigh'              => '',
                    'CarriageCapacityFlag'    => '',
                    'ValidPUC'                => 'Y',
                    'TrailerTowedBy'          => '',
                    'TrailerRegNo'            => '',
                    'NoOfTrailer'             => '',
                    'TrailerValLimPaxIDVDays' => '',
                    // 'TrailerValLimPaxIDVDays'  => '',
                    'TrailerChassisNo'         => '',
                    'TrailerMfgYear'            => '',
                    'SchoolBusFlag'         => '',
                    // 'BancaSegment'      => '',
                ],
                'InterestParty'     => [
                    'Code'     => $proposal->is_vehicle_finance == '1' ? 'HY' : '',
                    'BankName' => $proposal->is_vehicle_finance == '1' ? strtoupper($proposal->name_of_financer) : '',
                ],
                'AdditionalBenefit' => [
                    'Discount'                                 => '',
                    'ElectricalAccessoriesValues'              => $IsElectricalItemFitted == 'true' ? $ElectricalItemsTotalSI : '',
                    'NonElectricalAccessoriesValues'           => $IsNonElectricalItemFitted == 'true' ? $NonElectricalItemsTotalSI : '',
                    'FibreGlassTank'                           => '',
                    'GeographicalArea'                         => '',
                    'PACoverForUnnamedPassengers'              => $IsPAToUnnamedPassengerCovered == 'true' ? $PAToUnNamedPassengerSI : '',
                    'LegalLiabilitytoPaidDriver'               => $IsLLPaidDriver,
                    'LegalLiabilityForOtherEmployees'          => '',
                    'LegalLiabilityForNonFarePayingPassengers' => '',
                    'UseForHandicap'                           => '',
                    'AntiThiefDevice'                          => '',
                    'NCB'                                      => $motor_applicable_ncb,
                    'RestrictedTPPD'                           => '',
                    'PrivateCommercialUsage'                   => '',
                    'CPAYear' => $cpa_year,
                    'CPADisc'                                  =>'',
                    'IMT23'                                    => '',
                    'CPAReq'                                   => $CPAReq,
                    'CPA'                                      => [
                        'CPANomName'       => $cpa_nom_name,
                        'CPANomAge'        => $cpa_nom_age,
                        'CPANomAgeDet'     => $cpa_nom_age_det,
                        'CPANomPerc'       => $cpa_nom_perc,
                        'CPARelation'      => $cpa_relation,
                        'CPAAppointeeName' => $cpa_appointee_name,
                        'CPAAppointeRel'   => $cpa_appointe_rel
                    ],
                    'NPAReq'              => 'N',
                    'NPA'                 => [
                        'NPAName'         => '',
                        'NPALimit'        => '',
                        'NPANomName'      => '',
                        'NPANomAge'       => '',
                        'NPANomAgeDet'    => '',
                        'NPARel'          => '',
                        'NPAAppinteeName' => '',
                        'NPAAppinteeRel'  => '',
                    ],
                    'ExistingPACover'   => 'N',
                    'PA'    => [
                        'InsurerName'   => '',
                        'ExistingPAPolicyNo'    => '',
                        'ExistingPASumInsured'  => '',
                        'AlternatePolicyExpiryDate' => '',
                        'ValidLicense'      => ''
                    ],
                    'ZIMT34ID'  =>  '',
                ],
                'AddonReq'          => (config('IC.FUTURE_GENERALI.V2.CAR.IS_ADDON_ENABLE') == 'N' ? 'N' : (($premium_type == 'third_party' || $fg_applicable_addons == null) ? 'N' : 'Y')), // FOR ZERO DEP value is Y and COVER CODE is PLAN1
                'Addon'             => (((config('IC.FUTURE_GENERALI.V2.CAR.IS_ADDON_ENABLE') == 'N') || empty($fg_applicable_addons)) ? ['CoverCode' => ''] : $fg_applicable_addons),
                'PreviousTPInsDtls' => [
                    'PreviousInsurer' => ($premium_type == 'own_damage') ? $previous_tp_insurer_code : '',
                    'TPPolicyNumber' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_number : '',
                    'TPPolicyEffdate' => ($premium_type == 'own_damage') ? date('d/m/Y', strtotime($proposal->tp_start_date)) : '',
                    'TPPolicyExpiryDate' => ($premium_type == 'own_damage') ? date('d/m/Y', strtotime($proposal->tp_end_date)) : ''

                ],
                'PreviousInsDtls'   => [
                    'UsedCar'        => $usedCar,
                    'UsedCarList'    => [
                        'PurchaseDate'    => ($usedCar == 'Y') ? date('d/m/Y', strtotime($requestData->vehicle_register_date)) : '',
                        'InspectionRptNo' => '',
                        'InspectionDt'    => '',
                    ],
                    'RollOver'       => $rollover,
                    'RollOverList'   => [
                        'PolicyNo'              => ($rollover == 'N') ? '' :$prev_policy_number,
                        'InsuredName'           => ($rollover == 'N') ? '' :$previous_insurer_name,
                        'PreviousPolExpDt'      => ($rollover == 'N') ? '' :$PreviousPolExpDt,
                        'ClientCode'            => ($rollover == 'N') ? '' :$ClientCode,
                        'Address1'              => ($rollover == 'N') ? '' :$prev_ic_address1,
                        'Address2'              => ($rollover == 'N') ? '' :$prev_ic_address2,
                        'Address3'              => '',
                        'Address4'              => '',
                        'Address5'              => '',
                        'PinCode'               => ($rollover == 'N') ? '' :$prev_ic_pincode,
                        'InspectionRptNo'       => '',
                        'InspectionDt'          => '',
                        'NCBDeclartion'         => ($rollover == 'N') ? 'N' :$ncb_declaration,
                        'ClaimInExpiringPolicy' => ($rollover == 'N') ? 'N' :$claimMadeinPreviousPolicy,
                        'NCBInExpiringPolicy'   => ($rollover == 'N') ? 0 :$motor_no_claim_bonus,
                        'TypeOfDoc'             =>'',
                        'NoOfClaims'            =>''
                    ],
                    'NewVehicle'     => $NewCar,
                    'NewVehicleList' => [
                        'InspectionRptNo' => '',
                        'InspectionDt'    => '',
                    ],
                ],
                'ZLLOTFLG'     =>'',
                'GARAGE'        =>'',
                'ZREFRA'        =>'',
                'ZREFRB'        =>'',
                'ZIDVBODY'      =>'',
                'COVERNT'       =>'',
                'CNTISS'        =>'',
                'ZCVNTIME'      =>'',
                'AddressSeqNo'  =>'',

            ],
        ];

        if(strtoupper($requestData->previous_policy_type=='NOT SURE'))
        {
            $quote_array['Risk']['PreviousInsDtls']['PreviousInsurer']='';
            $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyNumber']= '';
            $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyEffDate'] = '';
            $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyExpiryDate'] = '';
        }

        $additional_data = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'GetQuote',
            'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><GetQuote xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></GetQuote></Body></Envelope>',
            'method' => 'Premium Calcuation',
            'section' => 'car',
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name
        ];
        // dd($quote_array);
        $get_response = getWsData(config('IC.FUTURE_GENERALI.V2.CAR.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
        // dd($get_response);
        $data=$get_response['response'];
       

        if($data)
        {
            try{
                $quote_output=html_entity_decode($data);
                $quote_output=XmlToArray::convert($quote_output);
            }
            catch(Exception $e)
            {
                return [
                    'premium_amount'=>0,
                    'status'=>false,
                    'webserviceid'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message'=>'Error while Processing response.',
                ];
            }
            $quote_output=html_entity_decode($data);
            $quote_output=XmlToArray::convert($quote_output);
            // dd($quote_output);
            if(isset($quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Client']['Status']) && $quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Client']['Status']=='Successful')
            {
                $data=$quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Client'];
                $uid = time() . rand(10000, 99999);
                        //break in
                        if($is_breakin=='Y' && in_array($premium_type,['own_damage','comprehensive']))
                        {
                            
                          
                            $inspection_data['fullname']=($proposal->owner_type == 'I')? $proposal->first_name." ".$proposal->last_name : $proposal->last_name.' .';
                            $inspection_data['email_addr']=$proposal->email;
                            $inspection_data['mobile_no']= $proposal->mobile_number;
                            $inspection_data['address']= $proposal->address_line1.",".$proposal->address_line2.",".$proposal->address_line3;
                            $inspection_data['regNumber']= str_replace('-','',$proposal->vehicale_registration_number);
                            $inspection_data['make']=$mmv_data->make;
                            $inspection_data['brand'] = $mmv_data->vehicle_code;
                            $inspection_data['companyId'] = config('IC.FUTURE_GENERALI.V2.CAR.COMPANY_ID_MOTOR');
                            $inspection_data['branchId']  = config('IC.FUTURE_GENERALI.V2.CAR.BRANCH_ID_MOTOR');
                            $inspection_data['refId'] = 'FG' . rand(00000, 99999);
                            $inspection_data['appId'] = config('IC.FUTURE_GENERALI.V2.CAR.APP_ID_MOTOR');
                            $inspection_data['enquiryId'] = $enquiryId;
                            $inspection_data['company_name'] = 'Future Generali India Insurance Co. Ltd.';
                            $inspection_data['Vehicle_category'] = 'car';
                            $inspection_data['appUserId'] = config('IC.FUTURE_GENERALI.V2.CAR.APP_USER_ID');

                            $proposaql_date = date('Y-m-d H:i:s');
                            $proposal_data = UserProposal::find($proposal->user_proposal_id);
                          
                          
                            $inspection_response_array = create_request_for_inspection_through_live_check($inspection_data);
                     
                            if ($inspection_response_array) {
                                
                                $inspection_response_array_output = array_change_key_case_recursive(json_decode($inspection_response_array, TRUE));
                            
                                if ($inspection_response_array_output['status']['code'] == 200) {
                                    $breakin_response = [
                                        'live_check_reference_number' => $inspection_response_array_output['data']['refid'],
                                        'inspection_message'    => isset($inspection_response_array_output['data']['status']) ? $inspection_response_array_output : '',
                                        'inspection_reference_id_created_date' => $proposal_data,
                                    ];
                                  
                                    $is_breakin_case='Y';
                                    $save=UserProposal::where('user_proposal_id',$proposal->user_proposal_id)->update([
                                        'is_breakin_case'=>$is_breakin_case
                                    ]);

                                    DB::table('cv_breakin_status')->insert([
                                        'user_proposal_id' => $proposal->user_proposal_id,
                                        'ic_id'   => $productData->company_id,
                                        'breakin_number'   => $breakin_response['live_check_reference_number'],
                                        'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_status_final'  => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_check_url' => config('IC.FUTURE_GENERALI.V2.CAR.BREAKIN_CHECK_URL'),
                                        'created_at'    => date('Y/m/d H:m:s'),
                                        'updated_at'    => date('Y/m/d H:m:s'),
                                    ]);
                                    updateJourneyStage([
                                        'user_product_journey_id'   => $enquiryId,
                                        'ic_id' => $productData->company_id,
                                        'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                        'proposal_id'   => $proposal->user_proposal_id
                                    ]);
                    
                                    if(isset($inspection_response_array_output['data']['status']) && $inspection_response_array_output['data']['status']=='company-approved')
                                    {
                                        $quote_array_CRT['Uid']=$uid;
                                        $quote_array_CRT['PolicyHeader']['strPolicyQuoteNumber']=$data['QuotationNo'];
                                        $quote_array_CRT['Risk']['PreviousInsDtls']['RollOverList']['InspectionRptNo']=$breakin_response['live_check_reference_number'];
                                        if(strtoupper($requestData->previous_policy_type=='NOT SURE'))
                                        {
                                            $quote_array_CRT['Risk']['PreviousInsDtls']['PreviousInsurer']='';
                                            $quote_array_CRT['Risk']['PreviousTPInsDtls']['TPPolicyNumber']= '';
                                            $quote_array_CRT['Risk']['PreviousTPInsDtls']['TPPolicyEffDate'] = '';
                                            $quote_array_CRT['Risk']['PreviousTPInsDtls']['TPPolicyExpiryDate'] = '';
                                        }
                                        $additional_data = [
                                            'requestMethod' => 'post',
                                            'enquiryId' => $enquiryId,
                                            'soap_action' => 'GetQuote',
                                            'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><GetQuote xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></GetQuote></Body></Envelope>',
                                            'method' => STAGE_NAMES['PROPOSAL_DRAFTED'],
                                            'section' => 'car',
                                            'transaction_type' => 'proposal',
                                            'productName'  => $productData->product_name
                                        ];
                                        // dd($quote_array);
                                        // $get_response = getWsData(config('IC.FUTURE_GENERALI.V2.CAR.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
                                        // dd($get_response);
                                        // $data=$get_response['response'];
                                        // dd($get_response);
                                        // $additional_data = [
                                        //     'requestMethod' => 'post',
                                        //     'enquiryId' => $enquiryId,
                                        //     'soap_action' => 'CreateProposal',
                                        //     'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreateProposal xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreateProposal></Body></Envelope>',
                                        //     'method' => 'Submit Proposal:CRT',
                                        //     'section' => 'car',
                                        //     'transaction_type' => 'proposal',
                                        //     'productName'  => $productData->product_name
                                        // ];
                                        // $get_response = getWsData(config('IC.FUTURE_GENERALI.V2.CAR.END_POINT_URL'), $quote_array_CRT, 'future_generali', $additional_data);
                                       
                                        // $quote_output=html_entity_decode($data);
                                        // $quote_output=XmlToArray::convert($quote_output);
                                        if (isset($quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Client']))
                                        {
                                            $enq=$quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Client'];
                                            $data = $quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Policy'];
                                            foreach ($data['NewDataSet']['Table1'] as $key => $cover) {
                                                $cover = array_map('trim', $cover);
                                                $value = $cover['BOValue'];
                                                if ($cover['Code'] == 'DISCPERC' && $cover['Type'] == 'OD') {
                                                    $discount = (int)$value;
                                                }
                                            }
                                           
                                            $quote_array_CRT['Risk']['AdditionalBenefit']['Discount'] = $discount;
                        
                                            $data_CRT_method=['quotation_no'=>$enq['QuotationNo'],
                                            'client_id'=>$enq['ClientId'],
                                            'crt_method'=>$quote_array_CRT
                                            ];
                                            $client=json_encode($data_CRT_method);
                                            $value=UserProposal::where('user_product_journey_id',$enquiryId)->get()->toArray();
                                            if($value!=null)
                                            {
                                                UserProposal::where('user_product_journey_id',$enquiryId)
                                                ->update([
                                                    'additional_details_data'=> $client,
                                                ]);
                                            }
                                            else
                                            {
                                                UserProposal::insert([
                                                    'user_product_journey_id'=>$enquiryId,
                                                    'additional_details_data'=> $client,
                                                ]);
                                            }
                                        }
                                        if($quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Client'] && $quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Client']['Status']=='fail')
                                        {
                                            return [
                                                'premium_amount' => 0,
                                                'status'  => false,
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'message' => $quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['ErrorMessage'],
                                            ];
                                        }
                                        if(isset($quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Policy']))
                                        {
                                            $quote_output=$quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Policy'];
                                            if(isset($quote_output['VehicleIDV']))
                                            {
                                                $quote_output['VehicleIDV']=str_replace('-','',$quote_output['VehicleIDV']);
                                            }
                                            if($quote_output['Status']=='Fail')
                                            {
                                                if($quote_output['Error'])
                                                {
                                                    return[
                                                        'premium_amount' => 0,
                                                        'Status'  => false,
                                                        'webservice_id' => $get_response['webservice_id'],
                                                        'table' => $get_response['table'],
                                                        'message' => $quote_output['Error'],

                                                    ];
                                                }
                                                else if($quote_output['ErrorMessage'])
                                                {
                                                    return[
                                                        'premium_amount' => 0,
                                                        'status'  => false,
                                                        'webservice_id' => $get_response['webservice_id'],
                                                        'table' => $get_response['table'],
                                                        'message' => $quote_output['ErrorMessage'],
                                                    ];
                                                }
                                                else if($quote_output['ValidationError'])
                                                {
                                                    return[
                                                        'premium_amount' => 0,
                                                        'status'  => false,
                                                        'webservice_id' => $get_response['webservice_id'],
                                                        'table' => $get_response['table'],
                                                        'message' => $quote_output['ValidationError'],
                                                    ];
                                                }

                                            }
                                            else{
                                                $total_idv=($premium_type=='third_prty') ? 0 :  round((int)$quote_output['VehicleIDV']);
                                                $min_idv=ceil($total_idv * 0.9);
                                                $max_idv=floor($total_idv * 1.2);

                                                $total_od_premium=0;
                                                $total_tp_premium=0;
                                                $od_premium=0;
                                                $tp_premium=0;
                                                $liability=0;
                                                $pa_owner=0;
                                                $pa_unnamed=0;
                                                $lpg_cng_amount=0;
                                                $lpg_cng_tp_amount=0;
                                                $electrical_amount=0;
                                                $non_electrical_amount=0;
                                                $ncb_discount=0;
                                                $discount_amount=0;
                                                $discperc=0;
                                                $zero_dep_amount=0;
                                                $eng_prot=0;
                                                $ncb_prot=0;
                                                $rsa=0;
                                                $tyre_secure=0;
                                                $return_to_invoice=0;
                                                $consumable=0;
                                                $basePremium=0;
                                                $total_od=0;
                                                $total_tp=0;
                                                $total_discount=0;
                                                $service_tax_od=0;
                                                $service_tax_tp=0;
                                                $total_addons=0;
                                                $liability_IMT29 = 0;

                                                foreach($quote_output['NewDataSet']['Table1'] as $key=>$cover)
                                                {
                                                    $cover=array_map('trim',$cover);
                                                    $value=$cover['BOValue'];

                                                    if($cover['Code'] == 'PrmDue' && $cover['Type']=='OD')
                                                    {
                                                        $total_od_premium=$value;
                                                    }
                                                    elseif($cover['Code']=='PrmDue' && $cover['Type']=='TP')
                                                    {
                                                        $total_tp_premium=$value;
                                                    }
                                                    elseif($cover['Code']=='ServeTax' && $cover['Type']=='OD')
                                                    {
                                                        $service_tax_od=$value;
                                                    }
                                                    elseif($cover['Code']=='ServeTax' && $cover['Type']=='TP')
                                                    {
                                                        $service_tax_tp=$value;
                                                    }
                                                    elseif($cover['Code']=='DISCPERC' && $cover['Type']=='OD')
                                                    {
                                                        $discperc=$value;
                                                    }
                                                    elseif($cover['Code']=='IDV' && $cover['Type']=='OD')
                                                    {
                                                        $od_premium=$value;
                                                    }
                                                    elseif($cover['Code']=='IDV' && $cover['Type']=='TP')
                                                    {
                                                        $tp_premium=$value;
                                                    }
                                                    elseif($cover['Code']=='LLDE' && $cover['Type']=='TP')
                                                    {
                                                        $liability=$value;
                                                    }
                                                    elseif($cover['Code']=='CPA' && $cover['Type']=='TP')
                                                    {
                                                        $pa_owner=$value;
                                                    }
                                                    elseif($cover['Code']=='IMT29' && $cover['Type']=='TP')
                                                    {
                                                        $liability_IMT29=$value;
                                                    }
                                                    elseif($cover['Code']=='APA' && $cover['Type']=='TP')
                                                    {
                                                        $pa_unnamed=$value;
                                                    }
                                                    elseif($cover['Code']=='CNG' && $cover['Type']=='OD')
                                                    {
                                                        $lpg_cng_amount=$value;
                                                    }
                                                    elseif($cover['Code']=='CNG' && $cover['Type']=='TP')
                                                    {
                                                        $lpg_cng_tp_amount=$value;
                                                    }
                                                    elseif($cover['Code']=='EAV' && $cover['Type']=='OD')
                                                    {
                                                        $electrical_amount=$value;
                                                    }
                                                    elseif($cover['Code']=='NEA' && $cover['Type']=='OD')
                                                    {
                                                        $non_electrical_amount=$value;
                                                    }
                                                    elseif($cover['Code']=='NCB' && $cover['Type']=='OD')
                                                    {
                                                        $ncb_discount=abs($value);
                                                    }
                                                    elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $discount_amount = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'ZDCNE') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'ZDCNT') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'ZDCET') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'ZDCETR') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'STRSA') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'STNCB') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'RSPBK') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                    elseif (($cover['Code'] == 'STZDP') && ($cover['Type'] == 'OD'))
                                                    {
                                                        $total_addons = (int)$value;
                                                    }
                                                }
                                                if($discperc > 0)
                                                {
                                                    $od_premium=$od_premium + $discount_amount;
                                                    $discount_amount=0;
                                                }

                                                $final_premium = $total_od_premium + $total_tp_premium;
                                                $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                                                $total_tp = $tp_premium + $pa_owner + $pa_unnamed + $liability + $lpg_cng_tp_amount + $liability_IMT29;
                                                $total_discount = $ncb_discount + $discount_amount;
                                                $total_od_premium = $total_od_premium - $service_tax_od;
                                                $total_tp_premium = $total_tp_premium - $service_tax_tp;
                                                $basePremium = $total_od_premium + $total_tp_premium;
                                                $totalTax = $service_tax_od  + $service_tax_tp;
                                            
                                                $total_premium_amount = $total_od_premium + $total_tp_premium + $total_addons;

                                                updateJourneyStage([
                                                    'user_product_journey_id'=>$enquiryId,
                                                    'ic_id'=>$productData->company_id,
                                                    'stage'=>STAGE_NAMES['PROPOSAL_DRAFTED'],
                                                    'proposal_id' => $proposal->user_proposal_id
                                                ]);
                                                $add_on_details =
                                                    [
                                                    'AddonReq'=>$quote_array['Risk']['AddonReq'],
                                                    'Addon'=>json_encode($quote_array['Risk']['Addon'])
                                                    ];
                                                
                                                    // $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                                                    // ->where('user_proposal_id', $proposal->user_proposal_id)
                                                    // ->update([
                                                    //     'proposal_no' => $uid,
                                                    //     'unique_proposal_id' => $uid,
                                                    //     'policy_start_date' =>  str_replace('/','-',$policy_start_date),
                                                    //     'policy_end_date' =>  str_replace('/','-',$policy_end_date),
                                                    //     'od_premium' => $total_od - $total_discount,//+ $total_addons,
                                                    //     'tp_premium' => $total_tp,
                                                    //     'total_premium' => $basePremium,
                                                    //     'addon_premium' => $total_addons,
                                                    //     'cpa_premium' => $pa_owner,
                                                    //     'service_tax_amount' => $totalTax,
                                                    //     'total_discount' => $total_discount,
                                                    //     'final_payable_amount' => round($final_premium),
                                                    //     'ic_vehicle_details' => '',
                                                    //     'discount_percent' => $discperc,
                                                    //     'product_code'   => json_encode($add_on_details),
                                                    //     'chassis_number' => $proposal->chassis_number
                                                    // ]);
                                                    $update_proposal = [
                                                        'proposal_no' => $uid,
                                                        'unique_proposal_id' => $uid,
                                                        'policy_start_date' =>  str_replace('/','-',$policy_start_date),
                                                        'policy_end_date' => str_replace('/','-',$policy_end_date),
                                                        'od_premium' => $total_od - $total_discount,//+ $total_addons,
                                                        'tp_premium' => $total_tp,
                                                        'total_premium' => $basePremium,
                                                        'addon_premium' => $total_addons,
                                                        'cpa_premium' => $pa_owner,
                                                        'service_tax_amount' => $totalTax,
                                                        'total_discount' => $total_discount,
                                                        'final_payable_amount' => ($final_premium),
                                                        'ic_vehicle_details' => '',
                                                        'discount_percent' => $discperc,
                                                        'product_code'   => json_encode($add_on_details),
                                                        'tp_start_date' =>str_replace('/','-',$policy_start_date),
                                                        'tp_end_date' => str_replace('/','-',$policy_end_date),
                                                        'chassis_number' => $proposal->chassis_number
                                                    ];
                                                    if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                                                        unset($update_proposal['tp_start_date']);
                                                        unset($update_proposal['tp_end_date']);
                                                    }
                                                    $updateProposal = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($update_proposal);
            
                                       $proposal_data = UserProposal::find($proposal->user_proposal_id);

                                                FutureGeneraliPremiumDetailController::savePremiumDetailsv2($get_response['webservice_id']);
                                                return response()->json([
                                                    'status'    => true,
                                                    'msg'   => 'Proposal Submitted Successfully!',
                                                    'webservice_id' => $get_response['webservice_id'],
                                                    'table' => $get_response['table'],
                                                    'data'  => [
                                                        'proposalId'    => $proposal->user_proposal_id,
                                                        'userProductJourneyId'  => $proposal->user_product_journey_id,
                                                        'proposalNo'    => $uid,
                                                        'finalPayableAmount'    => ($final_premium),
                                                        'is_breakin'    => $is_breakin_case,
                                                        'inspection_number' => $breakin_response['live_check_reference_number'],
        
                                                    ]
                                                ]);
                                                
                                            }
                                        }
                                        else {
                                            $quote_output_array = $quote_output['Root']['Policy'];
                            
                                            if (isset($quote_output_array['Motor']['Message']) || isset($quote_output_array['ErrorMessage']) || isset($quote_output_array['status']) == 'Fail') {
                                                return [
                                                    'premium_amount'    => 0,
                                                    'status'            => false,
                                                    'webservice_id'     => $get_response['webservice_id'],
                                                    'table'             => $get_response['table'],
                                                    'message'           => isset($quote_output_array['ErrorMessage']) ? $quote_output_array['ErrorMessage'] : $quote_output_array['ErrorMessage'],
                                                ];
                                            }
                            
                                            if ($quote_output['Status']) {
                                                if (isset($quote_output['ValidationError'])) {
                                                    return [
                                                        'premium_amount'    => 0,
                                                        'status'            => false,
                                                        'webservice_id'     => $get_response['webservice_id'],
                                                        'table'             => $get_response['table'],
                                                        'message'           => $quote_output['ValidationError'],
                                                    ];
                                                } else {
                                                    return [
                                                        'premium_amount'    => 0,
                                                        'status'            => false,
                                                        'webservice_id'     => $get_response['webservice_id'],
                                                        'table'             => $get_response['table'],
                                                        'message'           => 'Error Occured',
                                                    ];
                                                }
                                            } else {
                                                return [
                                                    'premium_amount'    => 0,
                                                    'status'            => false,
                                                    'webservice_id'     => $get_response['webservice_id'],
                                                    'table'             => $get_response['table'],
                                                    'message'           => 'Error Occured',
                                                ];
                                            }
                                        }
                                      
                                    }
                                }
                                else {
                                    return [
                                        'type' => 'inspection',
                                        'staus' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message'   => 'Live Check API not reachable,Did not get any response',
                                        'inspection_id' =>  '',
                                    ];
                                }

                            }
                            else {
                                return [
                                    'type' => 'inspection',
                                    'staus' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message'   => 'Live Check API not reachable,Did not get any response',
                                    'inspection_id' =>  '',
                                ];
                            }
                        }
                        else
                        {
                            $quote_array_CRT['Uid']=$uid;
                            $quote_array_CRT['PolicyHeader']['strPolicyQuoteNumber']=$data['QuotationNo'];
                            $data = $quote_output['s:Body']['GetQuoteResponse']['GetQuoteResult']['Root']['Policy'];
                                            foreach ($data['NewDataSet']['Table1'] as $key => $cover) {
                                                $cover = array_map('trim', $cover);
                                                $value = $cover['BOValue'];
                                                if ($cover['Code'] == 'DISCPERC' && $cover['Type'] == 'OD') {
                                                    $discount = (int)$value;
                                                }
                                            }
                                           
                                            $quote_array_CRT['Risk']['AdditionalBenefit']['Discount'] = $discount;

                            if(strtoupper($requestData->previous_policy_type=='NOT SURE'))
                            {
                                $quote_array_CRT['Risk']['PreviousInsDtls']['PreviousInsurer']='';
                                $quote_array_CRT['Risk']['PreviousTPInsDtls']['TPPolicyNumber']= '';
                                $quote_array_CRT['Risk']['PreviousTPInsDtls']['TPPolicyEffDate'] = '';
                                $quote_array_CRT['Risk']['PreviousTPInsDtls']['TPPolicyExpiryDate'] = '';
                            }
                            $additional_data = [
                                'requestMethod' => 'post',
                                'enquiryId' => $enquiryId,
                                'soap_action' => 'CreateProposal',
                                'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreateProposal xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreateProposal></Body></Envelope>',
                                'method' => 'Proposal Submit',
                                'section' => 'car',
                                'transaction_type' => 'proposal',
                                'productName'  => $productData->product_name
                            ];
                            $get_response = getWsData(config('IC.FUTURE_GENERALI.V2.CAR.PROPOSAL_SUBMIT_URL'), $quote_array_CRT, 'future_generali', $additional_data);
                            $data=$get_response['response'];
                            $quote_output=html_entity_decode($data);
                            $quote_output=XmlToArray::convert($quote_output);
                            if(isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']) && $quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Policy']['Status'] == 'Fail')
                            {
                                if (isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Error'])) {
                                    return [
                                        'premium_amount' => 0,
                                        'status'  => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message'        => isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage']['Root']['ErrorMessage']) ? trim(($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage']['Root']['ErrorMessage'])) : trim(($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage'] ?? $quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Error'])) 
                                    ];
                                } elseif (isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage'])) {
                                    return [
                                       'premium_amount' => 0,
                                        'status'  => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message'        => isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage']['Root']['ErrorMessage']) ? trim(($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage']['Root']['ErrorMessage'])) : trim($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage']) 
                                    ];
                                } elseif (isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ValidationError'])) {
            
                                    return [
                                        'premium_amount' => 0,
                                        'status'  => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message'        => isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage']['Root']['ErrorMessage']) ? trim(($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage']['Root']['ErrorMessage'])) : trim(($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ErrorMessage'] ?? $quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['ValidationError']))
                                    ];
                                }
                            }
                            if (isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Client']))
                            {
                                $enq=$quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Client'];
                            
                                $data=['quotation_no'=>$enq['QuotationNo'],
                                'client_id'=>$enq['ClientId']];
                                
                                $client=json_encode($data);
                                $value= UserProposal::
                                where('user_product_journey_id',$enquiryId)->get()->toArray();
                                if($value!=null)
                                {
                                    $data= UserProposal::
                                    where('user_product_journey_id',$enquiryId)->update([
                                        'additional_details_data'=> $client,
                                    ]);
                                }
                                else
                                {
                                    $data= UserProposal::
                                    insert([
                                        'user_product_journey_id'=>$enquiryId,
                                        'additional_details_data'=> $client,
                                    ]);
                                }
                                
                            }
                            if($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult'] && $quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Client']['Status']=='fail')
                            {
                                return [
                                    'premium_amount' => 0,
                                    'status'  => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => $quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['ErrorMessage'],
                                ];
                            }
                            if(isset($quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Policy']))
                            {
                                $quote_output=$quote_output['s:Body']['CreateProposalResponse']['CreateProposalResult']['Root']['Policy'];
                                if(isset($quote_output['VehicleIDV']))
                                {
                                    $quote_output['VehicleIDV']=str_replace('-','',$quote_output['VehicleIDV']);
                                }
                                if($quote_output['Status']=='Fail')
                                {
                                    if($quote_output['Error'])
                                    {
                                        return[
                                            'premium_amount' => 0,
                                            'Status'  => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => $quote_output['Error'],

                                        ];
                                    }
                                    else if($quote_output['ErrorMessage'])
                                    {
                                        return[
                                            'premium_amount' => 0,
                                            'status'  => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => $quote_output['ErrorMessage'],
                                        ];
                                    }
                                    else if($quote_output['ValidationError'])
                                    {
                                        return[
                                            'premium_amount' => 0,
                                            'status'  => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => $quote_output['ValidationError'],
                                        ];
                                    }

                                }
                                else{
                                    $total_idv=($premium_type=='third_prty') ? 0 :  round((int)$quote_output['VehicleIDV']);
                                    $min_idv=ceil($total_idv * 0.9);
                                    $max_idv=floor($total_idv * 1.2);

                                    $total_od_premium=0;
                                    $total_tp_premium=0;
                                    $od_premium=0;
                                    $tp_premium=0;
                                    $liability=0;
                                    $pa_owner=0;
                                    $pa_unnamed=0;
                                    $lpg_cng_amount=0;
                                    $lpg_cng_tp_amount=0;
                                    $electrical_amount=0;
                                    $non_electrical_amount=0;
                                    $ncb_discount=0;
                                    $discount_amount=0;
                                    $discperc=0;
                                    $zero_dep_amount=0;
                                    $eng_prot=0;
                                    $ncb_prot=0;
                                    $rsa=0;
                                    $tyre_secure=0;
                                    $return_to_invoice=0;
                                    $consumable=0;
                                    $basePremium=0;
                                    $total_od=0;
                                    $total_tp=0;
                                    $total_discount=0;
                                    $service_tax_od=0;
                                    $service_tax_tp=0;
                                    $total_addons=0;

                                    foreach($quote_output['NewDataSet']['Table1'] as $key=>$cover)
                                    {
                                        $cover=array_map('trim',$cover);
                                        $value=$cover['BOValue'];

                                        if($cover['Code'] == 'PrmDue' && $cover['Type']=='OD')
                                        {
                                            $total_od_premium=$value;
                                        }
                                        elseif($cover['Code']=='PrmDue' && $cover['Type']=='TP')
                                        {
                                            $total_tp_premium=$value;
                                        }
                                        elseif($cover['Code']=='ServeTax' && $cover['Type']=='OD')
                                        {
                                            $service_tax_od=$value;
                                        }
                                        elseif($cover['Code']=='ServeTax' && $cover['Type']=='TP')
                                        {
                                            $service_tax_tp=$value;
                                        }
                                        elseif($cover['Code']=='DISCPERC' && $cover['Type']=='OD')
                                        {
                                            $discperc=$value;
                                        }
                                        elseif($cover['Code']=='IDV' && $cover['Type']=='OD')
                                        {
                                            $od_premium=$value;
                                        }
                                        elseif($cover['Code']=='IDV' && $cover['Type']=='TP')
                                        {
                                            $tp_premium=$value;
                                        }
                                        elseif($cover['Code']=='LLDE' && $cover['Type']=='TP')
                                        {
                                            $liability=$value;
                                        }
                                        elseif($cover['Code']=='CPA' && $cover['Type']=='TP')
                                        {
                                            $pa_owner=$value;
                                        }
                                        elseif($cover['Code']=='APA' && $cover['Type']=='TP')
                                        {
                                            $pa_unnamed=$value;
                                        }
                                        elseif($cover['Code']=='CNG' && $cover['Type']=='OD')
                                        {
                                            $lpg_cng_amount=$value;
                                        }
                                        elseif($cover['Code']=='CNG' && $cover['Type']=='TP')
                                        {
                                            $lpg_cng_tp_amount=$value;
                                        }
                                        elseif($cover['Code']=='EAV' && $cover['Type']=='OD')
                                        {
                                            $electrical_amount=$value;
                                        }
                                        elseif($cover['Code']=='NEA' && $cover['Type']=='OD')
                                        {
                                            $non_electrical_amount=$value;
                                        }
                                        elseif($cover['Code']=='NCB' && $cover['Type']=='OD')
                                        {
                                            $ncb_discount=abs($value);
                                        }
                                        elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD'))
                                        {
                                            $discount_amount = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'ZDCNE') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'ZDCNT') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'ZDCET') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'ZDCETR') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'STRSA') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'STNCB') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'RSPBK') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                        elseif (($cover['Code'] == 'STZDP') && ($cover['Type'] == 'OD'))
                                        {
                                            $total_addons = (int)$value;
                                        }
                                    }
                                    if($discperc > 0)
                                    {
                                        $od_premium=$od_premium + $discount_amount;
                                        $discount_amount=0;
                                    }

                                    $final_premium = $total_od_premium + $total_tp_premium;
                                    $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                                    $total_tp = $tp_premium + $pa_owner + $pa_unnamed + $liability + $lpg_cng_tp_amount;
                                    $total_discount = $ncb_discount + $discount_amount;
                                    $total_od_premium = $total_od_premium - $service_tax_od;
                                    $total_tp_premium = $total_tp_premium - $service_tax_tp;
                                    $basePremium = $total_od_premium + $total_tp_premium;
                                    $totalTax = $service_tax_od  + $service_tax_tp;
                                
                                    $total_premium_amount = $total_od_premium + $total_tp_premium + $total_addons;

                                    updateJourneyStage([
                                        'user_product_journey_id'=>$enquiryId,
                                        'ic_id'=>$productData->company_id,
                                        'stage'=>STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                        'proposal_id' => $proposal->user_proposal_id
                                    ]);
                                    $add_on_details =
                                        [
                                        'AddonReq'=>$quote_array['Risk']['AddonReq'],
                                        'Addon'=>json_encode($quote_array['Risk']['Addon'])
                                        ];
                                        // $quotation_no= $quote_array['PolicyHeader']['strpolicyquoteNumber'];
                                    
                                        // $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                                        // ->where('user_proposal_id', $proposal->user_proposal_id)
                                        // ->update([
                                        //     'proposal_no' => $uid,
                                        //     'unique_proposal_id' => $uid,
                                        //     'policy_start_date' =>  str_replace('/','-',$policy_start_date),
                                        //     'policy_end_date' =>  str_replace('/','-',$policy_end_date),
                                        //     'od_premium' => $total_od - $total_discount,//+ $total_addons,
                                        //     'tp_premium' => $total_tp,
                                        //     'total_premium' => $basePremium,
                                        //     'addon_premium' => $total_addons,
                                        //     'cpa_premium' => $pa_owner,
                                        //     'service_tax_amount' => $totalTax,
                                        //     'total_discount' => $total_discount,
                                        //     'final_payable_amount' => round($final_premium),
                                        //     'ic_vehicle_details' => '',
                                        //     'discount_percent' => $discperc,
                                        //     'product_code'   => json_encode($add_on_details),
                                        //     'chassis_number' => $proposal->chassis_number
                                        // ]);
                                        $update_proposal = [
                                            'proposal_no' => $uid,
                                            'unique_proposal_id' => $uid,
                                            'policy_start_date' =>  str_replace('/', '-', $policy_start_date),
                                            'policy_end_date' =>  str_replace('/','-',$policy_end_date),
                                            'od_premium' => $total_od - $total_discount, //+ $total_addons,
                                            'tp_premium' => $total_tp,
                                            'total_premium' => $basePremium,
                                            'addon_premium' => $total_addons,
                                            'cpa_premium' => $pa_owner,
                                            'service_tax_amount' => $totalTax,
                                            'total_discount' => $total_discount,
                                            'final_payable_amount' => ($final_premium),
                                            'ic_vehicle_details' => '',
                                            'discount_percent' => $discperc,
                                            'product_code'   => json_encode($add_on_details),
                                            'tp_start_date' => str_replace('/','-',$policy_start_date),
                                            'tp_end_date' => str_replace('/','-',$policy_end_date),
                                            'chassis_number' => $proposal->chassis_number
                                        ];
                                        if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                                            unset($update_proposal['tp_start_date']);
                                            unset($update_proposal['tp_end_date']);
                                        }
                                        $updateProposal = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($update_proposal);
                                    $proposal_data = UserProposal::find($proposal->user_proposal_id);

                                    FutureGeneraliPremiumDetailController::savePremiumDetailsv2($get_response['webservice_id']);
                                    return response()->json([
                                                'status' => true,
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'message'   => 'Proposal Submitted Successfully!',
                                                'data'  => [
                                                    'proposalId' => $proposal->user_proposal_id,
                                                    'userProductJourneyId'  => $proposal_data->user_product_journey_id,
                                                    'proposalNo'   => $uid,
                                                ],
    
                                            ]);
                                }
                            }
                            else {
                                $quote_output_array = $quote_output['Root']['Policy'];
                
                                if (isset($quote_output_array['Motor']['Message']) || isset($quote_output_array['ErrorMessage']) || isset($quote_output_array['status']) == 'Fail') {
                                    return [
                                        'premium_amount'    => 0,
                                        'status'            => false,
                                        'webservice_id'     => $get_response['webservice_id'],
                                        'table'             => $get_response['table'],
                                        'message'           => isset($quote_output_array['ErrorMessage']) ? $quote_output_array['ErrorMessage'] : $quote_output_array['ErrorMessage'],
                                    ];
                                }
                
                                if ($quote_output['Status']) {
                                    if (isset($quote_output['ValidationError'])) {
                                        return [
                                            'premium_amount'    => 0,
                                            'status'            => false,
                                            'webservice_id'     => $get_response['webservice_id'],
                                            'table'             => $get_response['table'],
                                            'message'           => $quote_output['ValidationError'],
                                        ];
                                    } else {
                                        return [
                                            'premium_amount'    => 0,
                                            'status'            => false,
                                            'webservice_id'     => $get_response['webservice_id'],
                                            'table'             => $get_response['table'],
                                            'message'           => 'Error Occured',
                                        ];
                                    }
                                } else {
                                    return [
                                        'premium_amount'    => 0,
                                        'status'            => false,
                                        'webservice_id'     => $get_response['webservice_id'],
                                        'table'             => $get_response['table'],
                                        'message'           => 'Error Occured',
                                    ];
                                }
                            }

                        }
                    }
            else {
                return [
                    'premium_amount'    => 0,
                    'status'            => false,
                    'webservice_id'     => $get_response['webservice_id'],
                    'table'             => $get_response['table'],
                    'message'           => 'Error Occured',
                ];
            }    
        } 
        else {
            return [
                'premium_amount'    => 0,
                'status'            => false,
                'webservice_id'     => $get_response['webservice_id'],
                'table'             => $get_response['table'],
                'message'           => 'Insurer Not Reachable',
            ];
        }
    }
}