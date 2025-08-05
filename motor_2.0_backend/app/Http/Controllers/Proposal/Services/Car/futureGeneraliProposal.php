<?php

namespace App\Http\Controllers\Proposal\Services\Car;

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

include_once app_path().'/Helpers/CarWebServiceHelper.php';

class futureGeneraliProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function get_fg_applicable_addons($age,$with_zd)
    {
        $ZCETR = $ZDCET = $ZDCNT = $STNCB = $ZDCNE = $ZDCNS = $STZDP = $STRSA = $RSPBK = 'N';
        $applicable_addons = [];
        if($age<=3)
        {
            $age_range = '3';
        }
        elseif($age>3 && $age<=5)
        {
            $age_range = '5';
        }
        elseif($age>5 && $age<=7)
        {
            $age_range = '7';
        }
        elseif($age > 7)
        {
            $with_zd = 'N';
            $age_range = '8';
        }
        
        $addon_data = FgCarAddonConfiguration::where('age','>=',$age_range)
                        ->where('with_zd', $with_zd)
                        ->get()
                        ->toArray();
        foreach ($addon_data as $key => $value) 
        {
           if($value['cover_code'] == 'ZCETR')
           {
              $ZCETR = 'Y';
           }
           elseif($value['cover_code'] == 'ZDCET') 
           {
              $ZDCET = 'Y';
           }
           elseif($value['cover_code'] == 'ZDCNT') 
           {
              $ZDCNT = 'Y';
           }
           elseif($value['cover_code'] == 'STNCB') 
           {
              $STNCB = 'Y';
           }
           elseif($value['cover_code'] == 'ZDCNE') 
           {
              $ZDCNE = 'Y';
           }
           elseif($value['cover_code'] == 'ZDCNS') 
           {
              $ZDCNS = 'Y';
           }
           elseif($value['cover_code'] == 'STZDP') 
           {
              $STZDP = 'Y';
           }
           elseif($value['cover_code'] == 'STRSA') 
           {
              $STRSA = 'Y';
           }
           elseif($value['cover_code'] == 'RSPBK')
           {
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
                        ->where('registration_no', str_replace("-", "",$proposal->vehicale_registration_number))
                        ->where('status', 'Active')
                        ->select('ic_identifier')
                        ->get()
                        ->toArray();
        if(isset($vehicle_block_data[0]))
        {
            $block_bool = false;
            $block_array = explode(',',$vehicle_block_data[0]->ic_identifier);
            if(in_array('ALL',$block_array))
            {
                $block_bool = true;
            }
            else if(in_array($request['companyAlias'],$block_array))
            {
               $block_bool = true; 
            }
            if($block_bool == true)
            {
                return  [
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => $proposal->vehicale_registration_number." Vehicle Number is Declined",
                    'request'           => [
                        'message'           => $proposal->vehicale_registration_number." Vehicle Number is Declined",
                    ]
                ];            
            }        
        }
        
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
    	$requestData = getQuotation($enquiryId);
    	$productData = getProductDataByIc($request['policyId']);
        
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
        if($requestData->ownership_changed== 'Y')
        {        
            if(!in_array($premium_type,['third_party','third_party_breakin']))
            {
                $premium_type_id = null;
                if(in_array($productData->premium_type_id,[1,4]))
                {
                    $premium_type_id = 4;
                }
                else if(in_array($productData->premium_type_id,[3,6]))
                {
                    $premium_type_id = 6;
                }
                
                $MasterPolicy = MasterPolicy::where('product_sub_type_id',1)
                                                ->where('insurance_company_id',28)
                                                ->where('premium_type_id',$premium_type_id)
                                                ->where('status','Active')
                                                ->get()
                                                ->first();
                if($MasterPolicy == false)
                {
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
        $corporate_service ='N';

        if(!empty($UserProductJourney->corporate_id) && !empty($UserProductJourney->domain_id) && config('IS_FUTURE_GENERALI_ENABLED_AFFINITY') == 'Y')
        {
                $corporate_service = 'Y';
        }

        $IsPos = 'N';
        $is_FG_pos_disabled = config('constants.motorConstant.IS_FG_POS_DISABLED');
        $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_FUTURE_GENERALI');
        //$is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
        $PanCardNo = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        $mmv = get_mmv_details($productData,$requestData->version_id,'future_generali');

        if($mmv['status'] == 1)
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

        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

        $rto_code = $requestData->rto_code;  
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code

        $rto_data = DB::table('future_generali_rto_master')
                ->where('rta_code', strtr($rto_code, ['-' => '']))
                ->first();


        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
        $premium_type_code = $premium_type;
        $is_breakin = 'N';
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
            $is_breakin = 'Y';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
            $is_breakin = 'N';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
            $is_breakin = 'Y';
        }

        if(!empty($requestData->previous_policy_expiry_date))
        {
            $expiry_date = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $today_date_comparison =date('Y-m-d');
            if($expiry_date >= new DateTime($today_date_comparison))
            {
                $is_breakin = 'N';
            }else
            {
                if($premium_type == 'third_party')
                {
                    $is_breakin = 'N';
                }else
                {
                    $is_breakin = 'Y';
                }
            }
        
        }
        $previous_policy_type = $requestData->previous_policy_type;
        if(in_array($previous_policy_type, ['Not sure', 'Third-party']) && !in_array($premium_type, ['third_party' , 'third_party_breakin']))
        {
           $is_breakin = 'Y';
        }
        
        
        
        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

         // car age calculation
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = $age / 12;
        $usedCar = 'N';

        $with_zd = ($productData->zero_dep == '0') ? 'Y' : 'N';
        $fg_applicable_addons = self::get_fg_applicable_addons($car_age, $with_zd);

        $addon = [];
        $addon_req = 'N';


        if ($productData->zero_dep == '0') 
        {

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
            if ($productData->product_identifier == 'STZDP' /*&& $fg_applicable_addons['STZDP'] == 'Y'*/) 
            {
                $addon[] = [
                    'CoverCode' => 'STZDP',
                ];
            } 
            elseif($productData->product_identifier == 'ZDCNS' /*&& $fg_applicable_addons['ZDCNS'] == 'Y'*/) 
            {
                $addon[] = [
                    'CoverCode' => 'ZDCNS',
                ];
            } 
            elseif($productData->product_identifier == 'ZDCNE' /*&& $fg_applicable_addons['ZDCNE'] == 'Y'*/) 
            {
                $addon[] = [
                    'CoverCode' => 'ZDCNE',
                ];
            } 
            elseif($productData->product_identifier == 'ZDCNT' /*&& $fg_applicable_addons['ZDCNT'] == 'Y'*/) 
            {
                $addon[] = [
                    'CoverCode' => 'ZDCNT',
                ];
            } 
            elseif($productData->product_identifier == 'ZDCET' /*&& $fg_applicable_addons['ZDCET'] == 'Y'*/) 
            {
                $addon[] = [
                    'CoverCode' => 'ZDCET',
                ];
            } 
            elseif($productData->product_identifier == 'ZCETR' /*&& $fg_applicable_addons['ZCETR'] == 'Y'*/) 
            {
                $addon[] = [
                    'CoverCode' => 'ZCETR',
                ];
            }

        }
        else
        {
            //standlone rsa  7 year
            //rsa plus personal belonging plus key loss 7 year
            /*if ($car_age <=7 && $productData->product_identifier=='rsa plus personal belonging plus key loss') {
                $addon[] = [
                    'CoverCode' => 'RSPBK',
                ];
            }*/
            if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') 
            {
                $addons = $selected_addons->applicable_addons;

                foreach ($addons as $value) 
                {
                    if (in_array('Road Side Assistance', $value) &&  $productData->product_identifier == 'basic' /*&& $fg_applicable_addons['STRSA'] == 'Y'*/) 
                    {
                        $addon[] = [
                            'CoverCode' => 'STRSA',
                        ];
                    } 
                    elseif (in_array('Road Side Assistance', $value) && $productData->product_identifier == 'RSPBK' /*&& $fg_applicable_addons['RSPBK'] == 'Y'*/) 
                    {
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

            if ($productData->product_identifier == 'RSPBK' /*&& $fg_applicable_addons['RSPBK'] == 'Y'*/) // if product_identifier is selected as RSPBK then RSPBK should be passed in CoverCode regardless of 'Road Side Assistance' selected in selected_addons or not
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
        if($premium_type == 'third_party' || $car_age > 7)
        {
            $addon = [];
        }
        $today_date =date('d-m-Y h:i:s');
        if ($requestData->business_type == 'newbusiness')
        {
            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = 'N';
            $ncb_declaration = 'N';
            $NewCar = 'Y';
            $rollover = 'N';
            $policy_start_date = date('d/m/Y');
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $PolicyNo = $insurer  = $previous_insurer_name = $prev_ic_address1 = $prev_ic_address2 = $prev_ic_pincode = $PreviousPolExpDt = $prev_policy_number = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date = '';
            $tp_start_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(strtr($policy_start_date, '/', '-'))) : '';
            $tp_end_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime($tp_start_date))))) : '';
            $contract_type = 'F13';
            $risk_type = 'F13';
            $reg_no = '';
            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000)
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    $contract_type = 'P13';
                    $risk_type = 'F13';
                }

                if($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    $contract_type = 'P13';
                    $risk_type = 'F13';
                }
                if(config('FUTURE_GENERALI_IS_NON_POS') == 'Y')
                {
                    $IsPos = 'N';
                    $PanCardNo  = '';
                    $contract_type = 'F13';
                    $risk_type = 'F13';
                }

            }
            elseif($pos_testing_mode === 'Y' && $quote->idv <= 5000000)
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                $contract_type = 'P13';
                $risk_type = 'F13';
            }

        }
        else
        {
            if($requestData->business_type == "breakin")
            {
                $policy_start_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($today_date)))));
            }
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
            }
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

            if($date_diff > 90)
            {
               $motor_expired_more_than_90_days = 'Y';
            }
            else
            {
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
                $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true); 
                $registration_number = $registration_no.'-'.$registration_number[2].'-'.$registration_number[3];
            } else {
                $registration_number = $reg_no;
            }

            if ($claimMadeinPreviousPolicy == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party')
            {
                $ncb_declaration = 'Y';
                $motor_no_claim_bonus = $requestData->previous_ncb;
                $motor_applicable_ncb = $requestData->applicable_ncb;
            }
            else
            {
                $ncb_declaration = 'N';
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
            }

            if($claimMadeinPreviousPolicy == 'Y' && $premium_type != 'third_party') {
                $motor_no_claim_bonus = $requestData->previous_ncb;
            }

            if($requestData->previous_policy_type == 'Third-party')
            {
                $ncb_declaration = 'N';
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
            }
            if($requestData->previous_policy_type != 'Not sure')
            {
                $previous_insure_name = DB::table('future_generali_prev_insurer')
                                    ->where('insurer_id', $proposal->previous_insurance_company)->first();
                $previous_insurer_name = $previous_insure_name->name;
                $ClientCode = $proposal->previous_insurance_company;
                $PreviousPolExpDt = date('d/m/Y', strtotime($corporate_vehicle_quotes_request->previous_policy_expiry_date));
                $prev_policy_number = $proposal->previous_policy_number;

                $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
                if(empty($insurer))
                {
                    return [
                        'status'  => false,
                        'message' => 'Insurer address not found - '.$proposal->insurance_company_name
                    ];
                }
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

            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && config('FUTURE_GENERALI_IS_NON_POS') != 'Y')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PVO';
                       $risk_type = 'FVO';

                    }
                    else
                    {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                    if($requestData->business_type == 'newbusiness')
                    {
                        $contract_type = 'P13';
                        $risk_type = 'F13';
                    }
                }

                if($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PVO';
                       $risk_type = 'FVO';

                    }
                    else
                    {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                }

            }
            elseif($pos_testing_mode === 'Y')
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                if($premium_type== "own_damage")
                {
                   $contract_type = 'PVO';
                   $risk_type = 'FVO';

                }
                else
                {
                    $contract_type = 'PPV';
                    $risk_type = 'FPV';
                }
            }

            if($is_pos_enabled == 'Y')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PVO';
                       $risk_type = 'FVO';

                    }
                    else
                    {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                    if($requestData->business_type == 'newbusiness')
                    {
                        $contract_type = 'P13';
                        $risk_type = 'F13';
                    }
                }
            }

            if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_FUTURE_GENERALI') == 'Y')
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                if($requestData->business_type == 'newbusiness')
                {
                    $contract_type = 'P13';
                    $risk_type = 'F13';
                }
                else
                {
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PVO';
                       $risk_type = 'FVO';
                    }
                    else
                    {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                }
            }

            $today_date =date('Y-m-d');
            if(new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date))
            {
                $policy_start_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            else if(new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date))
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                if(in_array($premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            }
            else
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                if(in_array($premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            }

            if($requestData->previous_policy_type == 'Not sure')
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                $usedCar = 'Y';
                $rollover = 'N';
                if(in_array($premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            }
            
            if($requestData->ownership_changed == 'Y')
            {
                if(!in_array($premium_type,['third_party','third_party_breakin']))
                {
                   $is_breakin = 'Y';
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


        if($requestData->business_type == 'rollover')
        {
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
        }

        if($requestData->business_type == 'breakin')
        {
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
        }

        $uid = time().rand(10000, 99999); //date('Ymshis').rand(0,9);


        if($requestData->vehicle_owner_type == "I")
        {
            if ($proposal->gender == "M")
            {
                $salutation = 'MR';
            }
            else
            {
                $salutation = 'MS';
            }
        }
        else
        {
            $salutation = '';
        }



        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $bifuel = 'false';

            if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
            {
                $accessories = ($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if($value['name'] == 'Electrical Accessories')
                    {
                        $IsElectricalItemFitted = 'true';
                        $ElectricalItemsTotalSI = $value['sumInsured'];
                    }
                    else if($value['name'] == 'Non-Electrical Accessories')
                    {
                        $IsNonElectricalItemFitted = 'true';
                        $NonElectricalItemsTotalSI = $value['sumInsured'];
                    }
                    else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                    {
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

            if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
                $additional_covers = $selected_addons->additional_covers;
                foreach ($additional_covers as $value) {
                   if($value['name'] == 'Unnamed Passenger PA Cover')
                   {
                        $IsPAToUnnamedPassengerCovered = 'true';
                        $PAToUnNamedPassenger_IsChecked = 'true';
                        $PAToUnNamedPassenger_NoOfItems = '1';
                        $PAToUnNamedPassengerSI = $value['sumInsured'];
                   }
                   if($value['name'] == 'LL paid driver')
                   {
                        $IsLLPaidDriver = '1';
                   }
                }
            }

            $IsAntiTheftDiscount = 'false';

            if($selected_addons && $selected_addons->discount != NULL && $selected_addons->discount != '')
            {
                $discount = $selected_addons->discount;
                foreach ($discount as $value) {
                   if($value['name'] == 'anti-theft device')
                   {
                        $IsAntiTheftDiscount = 'true';
                   }
                }
            }




            $cpa_selected = false;
            $cpa_year = '';
            if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
                $addons = $selected_addons->compulsory_personal_accident;
                foreach ($addons as $value) {
                    if(isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident'))
                    {
                        $cpa_selected = true;
                        if($requestData->business_type == 'newbusiness')
                        {
                            $cpa_year = isset($value['tenure'])? (string) $value['tenure'] :'1';
                        }
                    }
                 }
            }


            if ($requestData->vehicle_owner_type == 'I' && $cpa_selected == true && $premium_type != "own_damage")
            {
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
                    $cpa_year =  ''; //as per ic rollover case it should be blank#34364
                } 
            }
            else
            {
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
                    $cpa_year = ''; //as per ic rollover case it should be blank#34364
                }   
            }


        $previous_tp_insurer_code = '';
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

        // chassis_number should be 17 digits
        if(!empty($proposal->chassis_number))
        {
            $proposal->chassis_number = Str::padLeft($proposal->chassis_number, 17, '0');// adding 0 to complete string length of 17//sprintf("%06s",$proposal->chassis_number);
        }
        if ((env('APP_ENV') == 'local') && $requestData->business_type != 'newbusiness' && !in_array($premium_type,['third_party','third_party_breakin'])) {
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
                    }
                     else if ($key == 'electricleKit' && $value) {
                        $PreviousPolicy_electricleKit_Cover = true;
                    }
                     else if ($key == 'nonElectricleKit' && $value) {
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
         
        if($corporate_service !== 'Y')
        {
            $VendorCode = config('constants.IcConstants.future_generali.VENDOR_CODE_FUTURE_GENERALI');
            $VendorUserId =config('constants.IcConstants.future_generali.VENDOR_CODE_FUTURE_GENERALI') ;
            $AgentCode = config('constants.IcConstants.future_generali.AGENT_CODE_FUTURE_GENERALI') ;
            $BranchCode = ($IsPos == 'Y') ? '' : config('constants.IcConstants.future_generali.BRANCH_CODE_FUTURE_GENERALI');
        }else
        {
            $VendorCode = config('constants.IcConstants.future_generali.VENDOR_CODE_FUTURE_GENERALI_CORPORATE');
            $VendorUserId =config('constants.IcConstants.future_generali.VENDOR_CODE_FUTURE_GENERALI_CORPORATE') ;
            $AgentCode = config('constants.IcConstants.future_generali.AGENT_CODE_FUTURE_GENERALI_CORPORATE') ;
            $BranchCode = ($IsPos == 'Y') ? '' : config('constants.IcConstants.future_generali.BRANCH_CODE_FUTURE_GENERALI_CORPORATE');
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
                    'MobileNo'    => $proposal->mobile_number,
                    'EmailAddr'   => $proposal->email,
                ],
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
                    'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : str_replace('-', '', strtoupper($registration_number)),
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
                ],
                'AddonReq'          =>(config('IS_ADDON_ENABLE_FG')=='N' ? 'N': (($premium_type == 'third_party' || $addon==null )? 'N' : 'Y')), // FOR ZERO DEP value is Y and COVER CODE is PLAN1
                'Addon'             =>(config('IS_ADDON_ENABLE_FG')=='N' ? ['CoverCode' => ''] : ($addon!=null ? $addon : array_merge(['CoverCode'=>''],$addon)) ),
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
                    ],
                    'NewVehicle'     => $NewCar,
                    'NewVehicleList' => [
                        'InspectionRptNo' => '',
                        'InspectionDt'    => '',
                    ],
                ],
            ],
        ];

        if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
            $quote_array['Risk']['PreviousTPInsDtls']['PreviousInsurer'] = '';
            $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyNumber'] = '';
            $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyEffdate'] = '';
            $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyExpiryDate'] = '';
        }
        
       
        $additional_data = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'CreatePolicy',
            'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
            'method' => 'Premium Calculation',
            'section' => 'car',
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name
        ];
        
        $get_response = getWsData(config('constants.IcConstants.future_generali.END_POINT_URL_FUTURE_GENERALI'), $quote_array, 'future_generali', $additional_data);
        $data = $get_response['response'];
        if ($data) {
            try
            {
                $quote_output = html_entity_decode($data);
                $quote_output = XmlToArray::convert($quote_output);

            }catch(\Exception $e)
            {
                return [
                    'premium_amount' => 0,
                    'status'  => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Error while Processing response.',
                ];
            }
            $quote_output = html_entity_decode($data);
            $quote_output = XmlToArray::convert($quote_output);

            if( isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Status']) && $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Status'] == 'Fail') {
                return [
                    'premium_amount' => 0,
                    'status'  => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['ErrorMessage'],
                ];
            }

            if (isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy']))
            {

                $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];

                if (isset($quote_output['VehicleIDV'])) {
                    $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                }
                
                if ($quote_output['Status'] == 'Fail') {
                    if (isset($quote_output['Error'])) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'        => $quote_output['Error']
                        ];
                    } elseif (isset($quote_output['ErrorMessage'])) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'        => $quote_output['ErrorMessage']
                        ];
                    } elseif (isset($quote_output['ValidationError'])) {

                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'        => $quote_output['ValidationError']
                        ];
                    }
                }else{
                    $total_idv = ($premium_type == 'third_party') ? 0 : round($quote_output['VehicleIDV']);
                    $min_idv = ceil($total_idv * 0.9);
                    $max_idv = floor($total_idv * 1.2);

                    $total_od_premium = 0;
                    $total_tp_premium = 0;
                    $od_premium = 0;
                    $tp_premium = 0;
                    $liability = 0;
                    $pa_owner = 0;
                    $pa_unnamed = 0;
                    $lpg_cng_amount = 0;
                    $lpg_cng_tp_amount = 0;
                    $electrical_amount = 0;
                    $non_electrical_amount = 0;
                    $ncb_discount = 0;
                    $discount_amount = 0;
                    $discperc = 0;
                    $zero_dep_amount = 0;
                    $eng_prot = 0;
                    $ncb_prot = 0;
                    $rsa = 0;
                    $tyre_secure = 0;
                    $return_to_invoice = 0;
                    $consumable = 0;
                    $basePremium = 0;
                    $total_od = 0;
                    $total_tp = 0;
                    $total_discount = 0;
                    $service_tax_od = 0;
                    $service_tax_tp = 0;
                    $total_addons= 0;

                    foreach ($quote_output['NewDataSet']['Table1'] as $key => $cover)
                    {
                        $cover = array_map('trim', $cover);

                        $value = $cover['BOValue'];
                        if (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'OD'))
                        {
                            $total_od_premium = $value;
                        }
                        elseif (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'TP'))
                        {
                            $total_tp_premium = $value;
                        }
                        elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'OD'))
                        {
                            $service_tax_od = $value;
                        }
                        elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'TP'))
                        {
                            $service_tax_tp = $value;
                        }
                        elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD'))
                        {
                            $discperc = $value;
                        }

                        elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD'))
                        {
                            $od_premium = $value;
                        }
                        elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'TP'))
                        {
                            $tp_premium = $value;
                        }
                        elseif (($cover['Code'] == 'LLDE') && ($cover['Type'] == 'TP'))
                        {
                            $liability = $value;
                        }
                        elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP'))
                        {
                            $pa_owner = $value;
                        }
                        elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP'))
                        {
                            $pa_unnamed = $value;
                        }
                        elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'OD'))
                        {
                            $lpg_cng_amount = $value;
                        }
                        elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'TP'))
                        {
                            $lpg_cng_tp_amount = $value;
                        }
                        elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD'))
                        {
                            $electrical_amount = $value;
                        }
                        elseif (($cover['Code'] == 'NEA') && ($cover['Type'] == 'OD'))
                        {
                            $non_electrical_amount = $value;
                        }
                        elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD'))
                        {
                            $ncb_discount = abs($value);
                        }
                        elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD'))
                        {
                            $discount_amount = str_replace('-','',$value);
                        } elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        }  elseif (($cover['Code'] == 'ZDCNE') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        }  elseif (($cover['Code'] == 'ZDCNT') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        } elseif (($cover['Code'] == 'ZDCET') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        } elseif (($cover['Code'] == 'ZCETR') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        }
                        elseif (($cover['Code'] == 'STRSA') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        }
                        elseif (($cover['Code'] == 'STNCB') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        }
                        elseif (($cover['Code'] == 'RSPBK') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        }   elseif (($cover['Code'] == 'STZDP') && ($cover['Type'] == 'OD')) {
                            $total_addons=(int)$value;
                        }

                    }

                    if ($discperc > 0) {
                        $od_premium = $od_premium + $discount_amount;
                        $discount_amount = 0;
                    }

                    $final_premium =  $total_od_premium + $total_tp_premium;
                    $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                    $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount + $pa_owner;
                    $total_discount = $ncb_discount + $discount_amount;
                    //$basePremium = $total_od + $total_tp - $total_discount;
                    $total_od_premium = $total_od_premium - $service_tax_od;
                    $total_tp_premium = $total_tp_premium - $service_tax_tp;
                    $basePremium = $total_od_premium + $total_tp_premium;

                    $totalTax = $service_tax_od + $service_tax_tp;

                    // $total_addons = $zero_dep_amount + $eng_prot + $rsa + $tyre_secure + $return_to_invoice +$consumable +$ncb_prot;


                    $total_premium_amount = $total_od_premium + $total_tp_premium + $total_addons;

                     updateJourneyStage([
                                        'user_product_journey_id' =>$enquiryId,
                                        'ic_id' => $productData->company_id,
                                        'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                        'proposal_id' => $proposal->user_proposal_id
                                    ]);
                    $add_on_details = 
                    [ 
                        'AddonReq' => $quote_array['Risk']['AddonReq'],
                        'Addon'    => json_encode($quote_array['Risk']['Addon'])
                    ];
                    // $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                    //                 ->where('user_proposal_id', $proposal->user_proposal_id)
                    //                 ->update([
                    //                     'proposal_no' => $uid,
                    //                     'unique_proposal_id' => $uid,
                    //                     'policy_start_date' =>  str_replace('/','-',$policy_start_date),
                    //                     'policy_end_date' =>  str_replace('/','-',$policy_end_date),
                    //                     'tp_start_date' => $tp_start_date,
                    //                     'tp_end_date'   => $tp_end_date,
                    //                     'od_premium' => $total_od - $total_discount, //+ $total_addons,
                    //                     'tp_premium' => $total_tp,
                    //                     'total_premium' => $basePremium,
                    //                     'addon_premium' => $total_addons,
                    //                     'cpa_premium' => $pa_owner,
                    //                     'service_tax_amount' => $totalTax,
                    //                     'total_discount' => $total_discount,
                    //                     'final_payable_amount' => round($final_premium),
                    //                     'ic_vehicle_details' => '',
                    //                     'discount_percent' => $discperc,
                    //                     'product_code'   => json_encode($add_on_details),
                    //                     'chassis_number' => $proposal->chassis_number
                    //                 ]);

                    $update_proposal=[
                        'proposal_no' => $uid,
                        'unique_proposal_id' => $uid,
                        'policy_start_date' =>  str_replace('/','-',$policy_start_date),
                        'policy_end_date' =>  $requestData->business_type == 'newbusiness' ?  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 3 year - 1 days', '/', '-'))):  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-'))),
                        'tp_start_date' => $tp_start_date,
                        'tp_end_date'   => $tp_end_date,
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
                        'chassis_number' => $proposal->chassis_number
                    ];
                    if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                        unset($update_proposal['tp_start_date']);
                        unset($update_proposal['tp_end_date']);
                    }

                    $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update($update_proposal);

                    $proposal_data = UserProposal::find($proposal->user_proposal_id);

                    FutureGeneraliPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                    if($is_breakin == 'Y')

                    {
                        $is_breakin_case = 'Y';

                        $inspection_data['fullname'] = ($proposal->owner_type == 'I')? $proposal->first_name." ".$proposal->last_name : $proposal->last_name.' .';
                        $inspection_data['email_addr'] = $proposal->email;
                        $inspection_data['mobile_no'] = $proposal->mobile_number;
                        $inspection_data['address'] = $proposal->address_line1.",".$proposal->address_line2.",".$proposal->address_line3;
                        $inspection_data['regNumber'] = str_replace('-',' ',$proposal->vehicale_registration_number);
                        $inspection_data['make'] = $mmv_data->make;
                        $inspection_data['brand'] = $mmv_data->vehicle_code;
                        $inspection_data['companyId'] = config('constants.IcConstants.future_generali.COMPANY_ID_FUTURE_GENERALI_MOTOR');
                        $inspection_data['branchId'] = config('constants.IcConstants.future_generali.BRANCH_ID_FUTURE_GENERALI_MOTOR');
                        $inspection_data['refId'] = 'FG'.rand(00000,99999);
                        $inspection_data['appId'] = config('constants.IcConstants.future_generali.APP_ID_FUTURE_GENERALI_MOTOR');
                        $inspection_data['enquiryId'] = $enquiryId;
                        $inspection_data['company_name'] = 'Future Generali India Insurance Co. Ltd.';
                        $inspection_data['Vehicle_category'] = 'car';

                        $proposal_date = date('Y-m-d H:i:s');
                        $inspection_response_array = create_request_for_inspection_through_live_check($inspection_data);

                        if($inspection_response_array)
                        {
                            $inspection_response_array_output = array_change_key_case_recursive(json_decode($inspection_response_array, TRUE));
                            if($inspection_response_array_output['status']['code'] == 200)
                            {
                                $save = UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                ->update([
                                    'is_breakin_case' => $is_breakin_case
                                ]);

                                $breakin_response =
                                [
                                    'live_check_reference_number' => $inspection_response_array_output['data']['refid'],
                                    'inspection_message'=> isset($inspection_response_array_output['data']['status']) ? $inspection_response_array_output['data']['status'] : '',
                                    'inspection_reference_id_created_date' => $proposal_date,

                                ];

                                DB::table('cv_breakin_status')->insert([
                                    'user_proposal_id' => $proposal->user_proposal_id,
                                    'ic_id' => $productData->company_id,
                                    'breakin_number' =>  $breakin_response['live_check_reference_number'],
                                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);

                                updateJourneyStage([
                                    'user_product_journey_id' => $enquiryId,
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                    'proposal_id' => $proposal->user_proposal_id,
                                ]);


                                return response()->json([
                                    'status' => true,
                                    'msg' => "Proposal Submitted Successfully!",
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'data' => [
                                        'proposalId' => $proposal->user_proposal_id,
                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                        'proposalNo' => $uid,
                                        'finalPayableAmount' => ($final_premium),
                                        'is_breakin' => $is_breakin_case,
                                        'inspection_number' => $breakin_response['live_check_reference_number'],
                                    ]
                                ]);
                            }
                            else
                            {

                                 return [
                                            'type' => 'inspection',
                                            'status'  => false,
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'message' => 'Live Check API not reachable',
                                            'inspection_id'=> '',
                                        ];

                            }

                        }
                        else
                        {

                             return [
                                        'type' => 'inspection',
                                        'status'  => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => 'Live Check API not reachable,Did not got any response',
                                        'inspection_id'=> '',
                                    ];
                        }

                    }
                    else
                    {
                        return response()->json([
                        'status' => true,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Proposal Submitted Successfully!",
                        'data' => [
                            'proposalId' =>  $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal_data->user_product_journey_id,
                            'proposalNo' => $uid,
                        ]
                    ]);
                    }





                }
            }
            else
            {

                $quote_output_array = $quote_output['Root']['Policy'];

                if (isset($quote_output_array['Motor']['Message']) || isset($quote_output_array['ErrorMessage']) || $quote_output_array['Status'] == 'Fail')
                {
                    return [
                        'premium_amount' => 0,
                        'status'  => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => isset($quote_output_array['ErrorMessage']) ? $quote_output_array['ErrorMessage'] : $quote_output_array['ErrorMessage'] ,
                    ];
                }

                if(isset($quote_output['Status']))
                {
                    if(isset($quote_output['ValidationError']))
                    {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'        => $quote_output['ValidationError']
                           ];
                    }
                    else
                    {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'        => 'Error Occured'
                           ];

                    }

                }
                else
                {
                    return [
                    'premium_amount' => 0,
                    'status'         => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'        => 'Error Occured'
                   ];

                }

            }
        }else{
            return [
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => 'Insurer Not Reachable'
            ];
        }

    }
    public static function renewalSubmit($proposal, $request)
    {
        if (config('constants.IcConstants.future_generali.FG_RENEWAL_ENABLED_FOR_CAR') == 'Y')
        {
            return self::renewalSubmitNew($proposal, $request);
        }
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'future_generali');

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $vehicleDetails = [
            'manf_name'             => $mmv->make,
            'model_name'            => $mmv->model,
            'version_name'          => $mmv->model,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->carrying_capacity,
            'cubic_capacity'        => $mmv->cc,
            'fuel_type'             => $mmv->fuel_code,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'CAR',
            'version_id'            => $mmv->ic_version_code,
        ];

        $policy_data = [
            "PolicyNo" => $proposal->previous_policy_number,
            "ExpiryDate" => $proposal->previous_policy_expiry_date,
            "RegistrationNo" => $proposal->vehicale_registration_number,
            "VendorCode" => 'webnew',
        ];
        $url = config('constants.IcConstants.future_generali.FG_RENEWAL_CAR_FETCH_POLICY_DETAILS');
        $get_response = getWsData($url, $policy_data, 'future_generali', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Renewal Fetch Policy Details',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
        $policy_data_response = $get_response['response'];

        if ($policy_data_response) {
            $quote_policy_output = XmlToArray::convert($policy_data_response);
            if (($quote_policy_output['PremiumBreakup']['Root']['Policy']['Status'] ?? '') !== 'Successful') {
                $quote_output = $quote_policy_output['PremiumBreakup']['Root']['Policy'];

                if ($quote_output['Status'] == 'Fail') {
                    if (isset($quote_output['Error'])) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                        ];
                    } elseif (isset($quote_output['ErrorMessage'])) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                        ];
                    } elseif (isset($quote_output['ValidationError'])) {

                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                        ];
                    }
                }
            } else {
                $output_data = $quote_policy_output;
                $quote_output = $quote_policy_output['PremiumBreakup']['Root']['Policy'];
                if (isset($quote_output['VehicleIDV'])) {
                    $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                }
                $total_od_premium = 0;
                $total_tp_premium = 0;
                $addon_premium = 0;
                $od_premium = 0;
                $tp_premium = 0;
                $liability = 0;
                $pa_owner = 0;
                $pa_unnamed = 0;
                $lpg_cng_amount = 0;
                $lpg_cng_tp_amount = 0;
                $electrical_amount = 0;
                $non_electrical_amount = 0;
                $ncb_discount = 0;
                $discount_amount = 0;
                $discperc = 0;
                $pa_paidDriver = 0;
                $zero_dep_amount = 0;
                $basePremium = 0;
                $total_od = 0;
                $total_tp = 0;
                $total_discount = 0;

                foreach ($quote_output['NewDataSet']['Table1'] as $key => $cover) {

                    $cover = array_map('trim', $cover);
                    $value = $cover['BOValue'];

                    if (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'OD')) {
                        $total_od_premium = $value;
                    } elseif (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'TP')) {
                        $total_tp_premium = $value;
                    } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD')) {
                        $od_premium = $value;
                    } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'TP')) {
                        $tp_premium = $value;
                    } elseif (($cover['Code'] == 'LLDE') && ($cover['Type'] == 'TP')) {
                        $liability = $value;
                    } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                        $pa_owner = $value;
                    } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                        $pa_unnamed = $value;
                    } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'OD')) {
                        $lpg_cng_amount = $value;
                    } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'TP')) {
                        $lpg_cng_tp_amount = $value;
                    } elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                        $electrical_amount = $value;
                    } elseif (($cover['Code'] == 'NEA') && ($cover['Type'] == 'OD')) {
                        $non_electrical_amount = $value;
                    } elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                        $ncb_discount = abs($value);
                    } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                        $discount_amount = (str_replace('-', '', $value));
                    } elseif (($cover['Code'] == 'PAPD') && ($cover['Type'] == 'TP')) {
                        $pa_paidDriver = ($value);
                    } elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                        $discperc = $value;
                    } elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZDCNE') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZDCNT') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZDCET') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZCETR') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'STRSA') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'RSPBK') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'STZDP') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    }

                    if (($cover['Code'] == 'STNCB') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    }
                }


                if ($discperc > 0) {
                    $od_premium = $od_premium + $discount_amount;
                    $discount_amount = 0;
                }


                $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount + $pa_paidDriver;
                $total_discount = $ncb_discount + $discount_amount;

                $total_addons = $output_data['PremiumDetail']['AddonPremium'];
                $base_premium_amount = $output_data['PremiumDetail']['Basepremium'];
                $totalTax = $output_data['PremiumDetail']['Servicetax'];
                $final_premium = $output_data['PremiumDetail']['totalPremium'];

                //other data
                $policy_start_date = $quote_policy_output['Root']['PolicyHeader']['PolicyStartDate'];
                $policy_end_date = DateTime::createFromFormat('d/m/Y h:i:s', $quote_policy_output['Root']['PolicyHeader']['PolicyEndDate'])->format('d-m-Y');
                $quote_no = $quote_policy_output['QuotationNo'];
                $policystartdatetime = DateTime::createFromFormat('d/m/Y h:i:s', $policy_start_date);
                $policy_start_date = $policystartdatetime->format('d-m-Y');

                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'proposal_no' => $quote_no,
                        'unique_proposal_id' => $quote_no,
                        'policy_start_date' =>   $policy_start_date,
                        'policy_end_date' =>   $policy_end_date,
                        'od_premium' => $total_od - $total_discount, //+ $total_addons,
                        'tp_premium' => $total_tp,
                        'total_premium' => $base_premium_amount,
                        'addon_premium' => $total_addons,
                        'cpa_premium' => $pa_owner,
                        'service_tax_amount' => $totalTax,
                        'total_discount' => $total_discount,
                        'final_payable_amount' => $final_premium,
                        'ic_vehicle_details' => '',
                        'discount_percent' => $discperc
                    ]);
                updateJourneyStage([
                    'user_product_journey_id' => $enquiryId,
                    'ic_id' => $productData->company_id,
                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                    'proposal_id' => $proposal->user_proposal_id
                ]);

                FutureGeneraliPremiumDetailController::saveRenewalPremiumDetails($get_response['webservice_id']);

                return response()->json([
                    'status' => true,
                    'msg' => "Proposal Submitted Successfully!",
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $enquiryId,
                        'proposalNo' => $quote_no,
                        'finalPayableAmount' => $final_premium,
                    ]
                ]);
            }
        }
    }
    public static function renewalSubmitNew($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'future_generali');

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $vehicleDetails = [
            'manf_name'             => $mmv->make,
            'model_name'            => $mmv->model,
            'version_name'          => $mmv->model,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->carrying_capacity,
            'cubic_capacity'        => $mmv->cc,
            'fuel_type'             => $mmv->fuel_code,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'CAR',
            'version_id'            => $mmv->ic_version_code,
        ];

        $policy_data = [
            "PolicyNo" => $proposal->previous_policy_number,
            "ExpiryDate" => $proposal->previous_policy_expiry_date,
            "RegistrationNo" => $proposal->vehicale_registration_number,
            "VendorCode" => config('constants.IcConstants.future_generali.FG_RENEWAL_VENDOR_CODE'),
        ];
        $url = config('constants.IcConstants.future_generali.FG_RENEWAL_CAR_FETCH_POLICY_DETAILS');
        $get_response = getWsData($url, $policy_data, 'future_generali', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Renewal Fetch Policy Details',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
        $policy_data_response = $get_response['response'];

        if ($policy_data_response) {
            $quote_policy_output = XmlToArray::convert($policy_data_response);
            
            if (($quote_policy_output['Policy']['Status'] ?? '') == 'Fail') {
                if ($quote_policy_output['Policy']['Status'] == 'Fail') {
                    if (isset($quote_policy_output['Error'])) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => $quote_policy_output['Error']
                        ];
                    } elseif (isset($quote_policy_output['ErrorMessage'])) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => $quote_policy_output['ErrorMessage']
                        ];
                    } 
                }
            }else {
                $output_data = $quote_policy_output;
                $quote_output = $quote_policy_output['PremiumBreakup']['NewDataSet']['Table'];
                if (isset($quote_output['VehicleIDV'])) {
                    $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                }
                $total_od_premium = 0;
                $total_tp_premium = 0;
                $od_premium = 0;
                $tp_premium = 0;
                $addon_premium = 0;
                $liability = 0;
                $legal_liability_to_employee = 0;
                $pa_owner = 0;
                $pa_unnamed = 0;
                $lpg_cng_amount = 0;
                $lpg_cng_tp_amount = 0;
                $electrical_amount = 0;
                $non_electrical_amount = 0;
                $ncb_discount = 0;
                $discount_amount = 0;
                $discperc = 0;
                $pa_paidDriver = 0;
                $zero_dep_amount = 0;
                $basePremium = 0;
                $total_od = 0;
                $total_tp = 0;
                $total_discount = 0;
                $tppd_discount = 0;
                $final_payable_amount = 0;

                foreach ($quote_output as $key => $cover) {

                    $cover = array_map('trim', $cover);
                    $value = $cover['BOValue'];

                    if ($cover['Code'] == 'Final Premium') {
                        $final_payable_amount = $value;
                    }
                    if (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'OD')) {
                        $total_od_premium = $value;
                    } elseif (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'TP')) {
                        $total_tp_premium = $value;
                    } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD')) {
                        $od_premium = $value;
                    } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'TP')) {
                        $tp_premium = $value;
                    } elseif ((in_array($cover['Code'],['LLDC'])) && ($cover['Type'] == 'TP')) {
                        $liability = $value;
                    } elseif ((in_array($cover['Code'],['LLDE'])) && ($cover['Type'] == 'TP')) {
                        $legal_liability_to_employee = $value;
                    } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                        $pa_owner = $value;
                    } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                        $pa_unnamed = $value;
                    } elseif ((in_array($cover['Code'],['CNG','CNGOD'])) && ($cover['Type'] == 'OD')) {
                        $lpg_cng_amount = $value;
                    } elseif ((in_array($cover['Code'],['CNG','CNGTP'])) && ($cover['Type'] == 'TP')) {
                        $lpg_cng_tp_amount = $value;
                    } elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'OD')) {
                        $service_tax_od = $value;
                    } elseif (($cover['Code'] == 'ServTax') && ($cover['Type'] == 'TP')) {
                        $service_tax_tp = $value;
                    } elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                        $electrical_amount = $value;
                    } elseif (($cover['Code'] == 'NEA') && ($cover['Type'] == 'OD')) {
                        $non_electrical_amount = $value;
                    } elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                        $ncb_discount = abs($value);
                    } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                        $discount_amount = (str_replace('-', '', $value));
                    } elseif (($cover['Code'] == 'PAPD') && ($cover['Type'] == 'TP')) {
                        $pa_paidDriver = ($value);
                    } elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                        $discperc = $value;
                    } elseif (($cover['Code'] == 'Restricted TPPD') && ($cover['Type'] == 'TP')) {
                        $tppd_discount = abs($value);
                    } elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZDCNE') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZDCNT') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZDCET') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'ZCETR') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'STRSA') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'RSPBK') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    } elseif (($cover['Code'] == 'STZDP') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    }

                    if (($cover['Code'] == 'STNCB') && ($cover['Type'] == 'OD')) {
                        $addon_premium = (int)$value;
                    }
                }


                if ($discperc > 0) {
                    $od_premium = $od_premium + $discount_amount;
                    $discount_amount = 0;
                }

                $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                $total_tp = $tp_premium + $liability + $legal_liability_to_employee + $pa_unnamed + $lpg_cng_tp_amount + $pa_owner + $pa_paidDriver;
                $total_discount = $ncb_discount + $discount_amount + $tppd_discount;
                $basePremium = $total_od + $total_tp + $addon_premium - $total_discount;
                $total_addons = $zero_dep_amount;
                $final_tp = $total_tp + $pa_owner;
                $od_base_premium = $total_od;
                $total_premium_amount = $total_od_premium + $total_tp_premium + $total_addons;
                $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));
                $totalTax = $basePremium * 0.18;
                $final_premium = $basePremium + $totalTax;

                //other data
                $today_date =date('d-m-Y h:i:s');
                if(new DateTime($requestData->previous_policy_expiry_date. ' 23:59:59') > new DateTime($today_date))
                {
                    $policy_start_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                }
                if($requestData->business_type == "breakin")
                {
                    $policy_start_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                }
                $policy_end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                $quote_no = $quote_policy_output['QuotationNo'];

                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'proposal_no' => $quote_no,
                        'unique_proposal_id' => $quote_no,
                        'policy_start_date' =>  $policy_start_date,
                        'policy_end_date' =>  $policy_end_date,
                        'od_premium' => $od_premium,
                        'tp_premium' => $tp_premium,
                        'total_premium' => $base_premium_amount,
                        'addon_premium' => $total_addons,
                        'cpa_premium' => $pa_owner,
                        'service_tax_amount' => $totalTax,
                        'total_discount' => $total_discount,
                        'final_payable_amount' => round($final_payable_amount),
                        'ic_vehicle_details' => '',
                        'discount_percent' => $discperc
                    ]);
                updateJourneyStage([
                    'user_product_journey_id' => $enquiryId,
                    'ic_id' => $productData->company_id,
                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                    'proposal_id' => $proposal->user_proposal_id
                ]);

                FutureGeneraliPremiumDetailController::saveRenewalPremiumDetails($get_response['webservice_id']);

                return response()->json([
                    'status' => true,
                    'msg' => "Proposal Submitted Successfully!",
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $enquiryId,
                        'proposalNo' => $quote_no,
                        'finalPayableAmount' => $final_premium,
                    ]
                ]);
            }
        }
    }
}
