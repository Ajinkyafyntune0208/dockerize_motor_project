<?php

namespace App\Http\Controllers\Proposal\Services\Bike\V1;

use App\Http\Controllers\SyncPremiumDetail\Bike\FutureGeneraliPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterPremiumType;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';

class FutureGeneraliProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $vehicle_block_data = DB::table('vehicle_block_data')
                        ->where('registration_no', str_replace("-", "",$proposal->vehicale_registration_number))
                        ->where('status', 'Active')
                        ->select('ic_identifier')
                        ->get()
                        ->toArray();
        
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 30,
            'address_2_limit'   => 30,
            'address_3_limit'   => 30,
            'address_4_limit'   => 30
        ];
        $getAddress = getAddress($address_data);

        $proposal->gender = (strtolower($proposal->gender) == "male" || $proposal->gender == "M") ? "M" : "F";
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
        
        // $mmv_data = DB::table('ic_version_mapping as icvm')
        // ->leftJoin('future_generali_model_master as cvrm', 'cvrm.vehicle_code', '=', 'icvm.ic_version_code')
        // ->where([
        //     'icvm.fyn_version_id' => trim($requestData->version_id),
        //     'icvm.ic_id' => trim($productData->company_id)
        // ])
        // ->select('icvm.*', 'cvrm.*')
        // ->first();

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

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
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

        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

        $IsPos = 'N';
        $is_FG_pos_disabled = config('constants.motorConstant.IS_FG_POS_DISABLED');
        $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_FUTURE_GENERALI');
        //$is_employee_enabled = config('IC.FUTURE_GENERALI.V1.BIKE.IS_EMPLOYEE_ENABLED');
        $PanCardNo = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        $rto_code = $requestData->rto_code;  
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code

        $rto_data = DB::table('future_generali_rto_master')
                ->where('rta_code', strtr($rto_code, ['-' => '']))
                ->first();

        
         // bike age calculation
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $bike_age = ceil($age / 12);
        
        if ((($bike_age > 15) || ($interval->y == 15 && ($interval->m > 0 || $interval->d > 0))))   //changes as per kit #20121
        {
            return [
                'status'         => false,
                'message'        => 'Quotes are not available for vehicle age above 15 years',
            ];
        }
       
        
        $addon = [];
        $addon_req = 'N';
        $usedCar = 'N';
        if(!empty($selected_addons['applicable_addons']))
        {
            foreach ($selected_addons['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Zero Depreciation' /*&& ($bike_age <= 5)*/ && ($productData->zero_dep == '0')) {
                    $addon_req = 'Y';
                    $addon[] = [
                        'CoverCode' => 'ZODEP'
                    ];
                }
                if ($data['name'] == 'Road Side Assistance' /*&& ($bike_age <= 3)*/ ) {
                    $addon_req = 'Y';
                    $addon[] = [
                        'CoverCode' => 'RODSA'
                    ];
                }
                if ($data['name'] == 'Consumable' /*&& ($bike_age <= 3)*/ ) {
                    $addon_req = 'Y';
                    $addon[] = [
                        'CoverCode' => 'CONSM'
                    ];
                }
            }
        }
        if ($requestData->business_type == 'newbusiness')
        {
            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = 'N';
            $ncb_declaration = 'N';
            $NewBike = 'Y';
            $rollover = 'N';
            $policy_start_date = date('d/m/Y');
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $PolicyNo = $insurer  = $previous_insurer_name = $prev_ic_address1 = $prev_ic_address2 = $prev_ic_pincode = $PreviousPolExpDt = $prev_policy_number = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date = '';
            $tp_start_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(strtr($policy_start_date, '/', '-'))) : '';
            $tp_end_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime($tp_start_date))))) : '';
            $contract_type = 'F15';
            $risk_type = 'F15';
            $reg_no = '';
            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000)
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    $contract_type = 'P15';
                    $risk_type = 'F15';
                }

                if($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    $contract_type = 'P15';
                    $risk_type = 'F15';
                }
                if(config('FUTURE_GENERALI_IS_NON_POS') == 'Y')
                {
                    $IsPos = 'N';
                    $PanCardNo  = '';
                    $contract_type = 'F15';
                    $risk_type = 'F15';
                }

            }
            elseif($pos_testing_mode === 'Y' && $quote->idv <= 5000000)
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                $contract_type = 'P15';
                $risk_type = 'F15';
            }

        }
        else
        {
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                
            }
            $rollover = 'Y';
            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = $requestData->is_claim;
            $ncb_declaration = 'N';
            if($requestData->previous_policy_type != 'Not sure')
            {
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
            
            $NewBike = 'N';
            $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';

            $registration_number = $reg_no;
            $registration_number = explode('-', $registration_number);

            if ($registration_number[0] == 'DL') {
                $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true); 
                $registration_number = $registration_no.'-'.$registration_number[2].'-'.$registration_number[3];
            } else {
                $registration_number = $reg_no;
            }

            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

            if($date_diff > 90)
            {
              $bike_expired_more_than_90_days = 'Y';
            }
            else
            {
                $bike_expired_more_than_90_days = 'N';
             
            }
            if ($claimMadeinPreviousPolicy == 'N' && $bike_expired_more_than_90_days == 'N' && $premium_type != 'third_party') {
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

           
            if($premium_type== "own_damage")
            {
               $contract_type = 'TWO';
               $risk_type = 'TWO';

            }
            else
            {   
                $contract_type = 'FTW';
                $risk_type = 'FTW';
            }

            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && config('FUTURE_GENERALI_IS_NON_POS') != 'Y')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                    if($requestData->business_type == 'newbusiness')
                    {
                        $contract_type = 'P15';
                        $risk_type = 'F15';
                    }
                }

                if($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                }

            }
            elseif($pos_testing_mode === 'Y')
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                if($premium_type== "own_damage")
                {
                   $contract_type = 'PWO';
                   $risk_type = 'TWO';

                }
                else
                {   
                    $contract_type = 'PTW';
                    $risk_type = 'FTW';
                }
            }
            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && config('FUTURE_GENERALI_IS_NON_POS') != 'Y')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                    if($requestData->business_type == 'newbusiness')
                    {
                        $contract_type = 'P15';
                        $risk_type = 'F15';
                    }
                }
            }
            if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_FUTURE_GENERALI') == 'Y')
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                if($requestData->business_type == 'newbusiness')
                {
                    $contract_type = 'P15';
                    $risk_type = 'F15';
                }
                else
                {
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';
                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                }
            }
            
    
            $today_date =date('Y-m-d'); 
            if(!empty($requestData->previous_policy_expiry_date) && new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date))
            {
                $policy_start_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            else if(!empty($requestData->previous_policy_expiry_date) && new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date))
            {
               $policy_start_date = date('d/m/Y', strtotime("+3 day")); 
            }
            else
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day")); 
            }

            if($requestData->previous_policy_type == 'Not sure')
            {
                $policy_start_date = date('d/m/Y', strtotime("+2 day")); 
                $usedCar = 'Y';
                $rollover = 'N';       
            }

            $policy_end_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
            
           
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

            if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
                $addons = $selected_addons->compulsory_personal_accident;
                foreach ($addons as $value) {
                    if(isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident'))
                    {
                        $cpa_selected = true;
                        $cpa_year = isset($value['tenure'])? (string) $value['tenure'] :'1';
                    }
                 }
            }

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
            
                }
            }



            if ($requestData->vehicle_owner_type == 'I' && $cpa_selected == true && $premium_type != 'own_damage' && $premium_type != 'own_damage_breakin') 
            {
                $CPAReq = 'Y';
                $cpa_nom_name = $proposal->nominee_name;
                $cpa_nom_age = $proposal->nominee_age;
                $cpa_nom_age_det = 'Y';
                $cpa_nom_perc = '100';
                $cpa_relation = $proposal->nominee_relationship;
                $cpa_appointee_name = '';
                $cpa_appointe_rel = '';
                /* if ($requestData->business_type == 'newbusiness')
                {
                    $cpa_year = '5';
                }
                else
                {
                    $cpa_year = '';
                } */
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
                ->where('client_code', $proposal->tp_insurance_company)->first()->tp_insurer_code;
            

            break;
            case "third_party":
            $cover_type = "LO";
            break;

        }
          if($productData->product_identifier == "BASIC")
          {
              $addon  = [
                'CoverCode' => ''
            ];
          }
          elseif($productData->product_identifier == 'WITH_BASIC_ADDON')
          {
              $addon[] = [
                  'CoverCode' => 'RODSA',
                  ];
              $addon[] = [
                  'CoverCode' => 'CONSM',
                  ];
          }

        // chassis_number should be 17 digits
        if(!empty($proposal->chassis_number))
        {
            $proposal->chassis_number = Str::padLeft($proposal->chassis_number, 17, '0');// adding 0 to complete string length of 17//sprintf("%06s",$proposal->chassis_number);
        }
        
        $quote_array = [
            '@attributes'  => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            ],
            'Uid'          => $uid,
            'VendorCode'   => config('IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE'),
            'VendorUserId' =>  config('IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE'),
            'PolicyHeader' => [
                'PolicyStartDate' => $policy_start_date,
                'PolicyEndDate'   => $policy_end_date,
                'AgentCode'       => config('IC.FUTURE_GENERALI.V1.BIKE.AGENT_CODE'),
                'BranchCode'      => ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.BIKE.BRANCH_CODE'),
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
                    'ModelCode'               => $mmv_data->vehicle_model_code,
                    'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : str_replace('-', '',$registration_number),
                    'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'ManufacturingYear'       => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'FuelType'                => $requestData->fuel_type == 'PETROL' ? 'P' : ($requestData->fuel_type == 'DIESEL' ? 'D' : ''),
                    'CNGOrLPG'                => [
                        'InbuiltKit'    =>  $requestData->fuel_type != 'Petrol' && $requestData->fuel_type != 'Diesel' ? 'N' : 'Y',
                        'IVDOfCNGOrLPG' => $bifuel == 'true' ? $BiFuelKitSi : '',
                    ],
                    'BodyType'                => 'SOLO',
                    'EngineNo'                => isset($proposal->engine_number) ? $proposal->engine_number : '',
                    'ChassiNo'                => isset($proposal->chassis_number) ? $proposal->chassis_number: '',
                    'CubicCapacity'           => $mmv_data->cc,
                    'SeatingCapacity'         => $mmv_data->seating_capacity,
                    'IDV'                     => $master_policy_id->idv,
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
                    'CPAYear' => (($requestData->business_type == 'newbusiness') ? $cpa_year : ''), 
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
                'AddonReq'          => (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? 'N' : $addon_req, // FOR ZERO DEP value is Y and COVER CODE is PLAN1
                'Addon'             => !empty($addon) && !(in_array($premium_type, ['third_party', 'third_party_breakin'])) ? $addon : ['CoverCode' => ''],
                'PreviousTPInsDtls' => [
                    'PreviousInsurer' => ($premium_type == 'own_damage') ? $previous_tp_insurer_code: '',
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
                    'NewVehicle'     => $NewBike,
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
            'section' => 'bike',
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name
        ];

        
        $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
        $data = $get_response['response'];

        if ($data) {
            $quote_output = html_entity_decode($data);
           
            $quote_output = XmlToArray::convert($quote_output);
            if(isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['ErrorMessage']) && $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['ErrorMessage'] != '')
            {
                return [
                    'premium_amount' => 0,
                    'status'         => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'        => $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['ErrorMessage']
                ];
            }

            if (isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'])) 
            {
                
                $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];
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

                    if (isset($quote_output['VehicleIDV'])) {
                        $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                    }
                    
                    $total_idv = ($premium_type == 'third_party') ? 0 : round($quote_output['VehicleIDV']);
                    $min_idv = ceil($total_idv * 0.9);      //changes as per kit #20121
                    $max_idv = floor($total_idv * 1.1);

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
                        elseif (($cover['Code'] == 'ZODEP') && ($cover['Type'] == 'OD'))
                        {
                            $zero_dep_amount = $value;
                        }
                        elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD'))
                        {
                            $ncb_discount = abs($value);
                        }
                        elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD'))
                        {
                            $discount_amount = str_replace('-','',$value);
                        }
                        elseif (($cover['Code'] == 'ENGPR') && ($cover['Type'] == 'OD'))
                        {
                            $eng_prot = $value;
                        }
                        elseif (($cover['Code'] == '00004') && ($cover['Type'] == 'OD'))
                        {
                            $ncb_prot = $value;
                        }
                        elseif (($cover['Code'] == 'RODSA') && ($cover['Type'] == 'OD'))
                        {
                            $rsa = $value;
                        }
                        elseif (($cover['Code'] == '00001') && ($cover['Type'] == 'OD'))
                        {
                            $tyre_secure = $value;
                        }
                        elseif (($cover['Code'] == '00006') && ($cover['Type'] == 'OD'))
                        {
                            $return_to_invoice = $value;
                        }
                        elseif (($cover['Code'] == 'CONSM') && ($cover['Type'] == 'OD'))
                        {
                            $consumable = $value;
                        }
                    }

                    if ($discperc > 0) {
                        $od_premium = $od_premium + $discount_amount;
                        $discount_amount = 0;
                    }

                    $total_addons_value = 0;

                    if($premium_type !== 'own_damage' && !(config('IC.FUTURE_GENERALI.V1.BIKE.DISABLE_RSA_CALCULATION') == 'Y'))
                    {
                        $tax_for_addons = 1.18;
                        $total_addons_value= ( $rsa * $tax_for_addons ) ;
                    }
                    $final_premium =  $total_od_premium + $total_tp_premium;// ( $total_od_premium + $total_addons_value ) + $total_tp_premium;
                    $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                    $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount + $pa_owner;;
                    $total_discount = $ncb_discount + $discount_amount;
                    //$basePremium = $total_od + $total_tp - $total_discount;
                    $total_od_premium = $total_od_premium - $service_tax_od;
                    $total_tp_premium = $total_tp_premium - $service_tax_tp;
                    $basePremium = $total_od_premium + $total_tp_premium;
                   
                    $totalTax = $service_tax_od + $service_tax_tp;
                    

                
                    $total_addons = $zero_dep_amount + $eng_prot + $rsa + $tyre_secure + $return_to_invoice +$consumable +$ncb_prot;
                    
                    
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
                    //                     'od_premium' => $total_od - $total_discount + $total_addons,
                    //                     'tp_premium' => $total_tp,
                    //                     'total_premium' => $basePremium,
                    //                     'addon_premium' => $total_addons,
                    //                     'cpa_premium' => $pa_owner,
                    //                     'service_tax_amount' => $totalTax,
                    //                     'total_discount' => $total_discount,
                    //                     'final_payable_amount' => $final_premium,
                    //                     'ic_vehicle_details' => '',
                    //                     'discount_percent' => $discperc,
                    //                     'product_code'   => json_encode($add_on_details),
                    //                     'chassis_number' => $proposal->chassis_number,                                     
                    //                     'additional_details_data' => json_encode($quote_array)
                    //                 ]);

                    $update_proposal=[
                        'proposal_no' => $uid,
                        'unique_proposal_id' => $uid,
                        'policy_start_date' =>  str_replace('/','-',$policy_start_date),
                        'policy_end_date' =>  $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ?  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-'))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?   date('d-m-Y', strtotime(strtr($policy_start_date . ' + 5 year - 1 days', '/', '-'))):  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-')))),
                        'tp_start_date' => $tp_start_date,
                        'tp_end_date'   => $tp_end_date,
                        'od_premium' => $total_od - $total_discount + $total_addons,
                        'tp_premium' => $total_tp,
                        'total_premium' => $basePremium,
                        'addon_premium' => $total_addons,
                        'cpa_premium' => $pa_owner,
                        'service_tax_amount' => $totalTax,
                        'total_discount' => $total_discount,
                        'final_payable_amount' => $final_premium,
                        'ic_vehicle_details' => '',
                        'discount_percent' => $discperc,
                        'product_code'   => json_encode($add_on_details),
                        'chassis_number' => $proposal->chassis_number,                                     
                        'additional_details_data' => json_encode($quote_array)                                    
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
                    
                    return response()->json([
                        'status' => true,
                        'message' => "Proposal Submitted Successfully!",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => [
                            'proposalId' =>  $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal_data->user_product_journey_id,
                        ]
                    ]);
                    


                    


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
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status'         => false,
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
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

        $policy_data = [
            "PolicyNo" => $proposal->previous_policy_number,
            "ExpiryDate" => $proposal->previous_policy_expiry_date,
            "RegistrationNo" => $proposal->vehicale_registration_number,
            "VendorCode" => config('IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_VENDOR_CODE'),
        ];
        $url = config('IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_BIKE_FETCH_POLICY_DETAILS');
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

                foreach ($quote_output as $key => $cover) {

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
                    } elseif ((in_array($cover['Code'],['LLDE','LLDC'])) && ($cover['Type'] == 'TP')) {
                        $liability = $value;
                    } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                        $pa_owner = $value;
                    } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                        $pa_unnamed = $value;
                    } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'OD')) {
                        $lpg_cng_amount = $value;
                    } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'TP')) {
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
                    }  elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
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
                $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount + $pa_owner + $pa_paidDriver;
                $total_discount = $ncb_discount + $discount_amount;
                $basePremium = $total_od + $total_tp + $addon_premium - $total_discount;
                $total_addons = $zero_dep_amount;
                $final_tp = $total_tp + $pa_owner;
                $od_base_premium = $total_od;
                $total_premium_amount = $total_od_premium + $total_tp_premium + $total_addons;
                $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));
                $totalTax = $basePremium * 0.18;
                $final_premium = $basePremium + $totalTax;

                //other data
                $today_date =date('Y-m-d');
                if(new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date))
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
                        'policy_end_date' =>  $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ?  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-'))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?   date('d-m-Y', strtotime(strtr($policy_start_date . ' + 5 year - 1 days', '/', '-'))):  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-')))),
                        'od_premium' => $od_premium,
                        'tp_premium' => $tp_premium,
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
}
