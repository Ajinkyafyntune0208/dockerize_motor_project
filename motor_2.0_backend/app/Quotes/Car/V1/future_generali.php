
<?php

use App\Models\IcVersionMapping;
use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\MotorManufacturer;
use App\Models\MotorModel;
use App\Models\MotorModelVersion;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use App\Models\MasterPolicy;
use App\Http\Controllers\Proposal\Services\Car\futureGeneraliProposal;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getQuoteV1($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    $isInspectionApplicable = 'N';
    $premium_type_array = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->select('premium_type_code','premium_type')
        ->first();
    if(($requestData->ownership_changed ?? '' ) == 'Y')
    {
        if(!in_array($premium_type_array->premium_type_code,['third_party','third_party_breakin']))
        {
            $isInspectionApplicable = 'Y';
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
    // mmv checks
    // $mmv_data = DB::table('ic_version_mapping as icvm')
    // ->leftJoin('future_generali_model_master as cvrm', 'cvrm.vehicle_code', '=', 'icvm.ic_version_code')
    // ->where([
    //     'icvm.fyn_version_id' => trim($requestData->version_id),
    //     'icvm.ic_id' => trim($productData->company_id)
    // ])
    // ->select('icvm.*', 'cvrm.*')
    // ->first();

    // $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);
    $vehicle_block_data = DB::table('vehicle_block_data')
                        ->where('registration_no', str_replace("-", "",$requestData->vehicle_registration_no))
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
        else if(in_array($productData->company_alias,$block_array))
        {
           $block_bool = true;
        }
        if($block_bool == true)
        {
            return  [
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => $requestData->vehicle_registration_no." Vehicle Number is Declined",
                'request'           => [
                    'message'           => $requestData->vehicle_registration_no." Vehicle Number is Declined",
                ]
            ];
        }
    }

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
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);


    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];
    }


    $mmv_data->manf_name = $mmv_data->make;
    $mmv_data->model_name = $mmv_data->model;
    $mmv_data->version_name = ''; //$mmv_data->model;
    $mmv_data->seating_capacity = $mmv_data->seating_capacity;
    $mmv_data->cubic_capacity = $mmv_data->cc;
    $mmv_data->fuel_type = $mmv_data->fuel_code;

    // car age calculation
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = DateTime::createFromFormat('d-m-Y', $vehicleDate);
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $car_age = ceil($age / 12);

    $tp_check = ($premium_type_array->premium_type_code == 'third_party' || $premium_type_array->premium_type_code == 'third_party_breakin') ? 'true' : 'false';
    if (($interval->y >= 15) && ($tp_check == 'true')){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
        ];
    }
    $with_zd = ($productData->zero_dep == '0') ? 'Y' : 'N';
    $fg_applicable_addons = futureGeneraliProposal::get_fg_applicable_addons($car_age,$with_zd);




    // if($productData->product_identifier == 'STZDP' && $fg_applicable_addons['STZDP'] == 'N')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'STZDP plan is not allowed for vehicle age '.$car_age. ' years',
    //         'request' => [
    //             'message' => 'STZDP plan is not allowed for vehicle age '.$car_age. ' years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    // elseif($productData->product_identifier == 'ZCETR' && $fg_applicable_addons['ZCETR'] == 'N')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'ZCETR plan is not allowed for vehicle age '.$car_age . ' years',
    //         'request' => [
    //             'message' => 'ZCETR plan is not allowed for vehicle age '.$car_age. ' years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    // elseif($productData->product_identifier == 'ZDCNS' && $fg_applicable_addons['ZDCNS'] == 'N')
    // {
    //     return  [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'ZDCNS plan is not allowed for vehicle age '.$car_age. ' years' ,
    //         'request' => [
    //             'message' => 'ZDCNS plan is not allowed for vehicle age '.$car_age. ' years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    // elseif($productData->product_identifier == 'ZDCNE' && $fg_applicable_addons['ZDCNE'] == 'N')
    // {
    //     return  [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'ZDCNE plan is not allowed for vehicle age '.$car_age. ' years',
    //         'request' => [
    //             'message' => 'ZDCNE plan is not allowed for vehicle age '.$car_age. ' years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    // elseif($productData->product_identifier == 'ZDCNT' && $fg_applicable_addons['ZDCNT'] == 'N')
    // {
    //     return  [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'ZDCNT plan is not allowed for vehicle age '.$car_age. ' years',
    //         'request' => [
    //             'message' => 'ZDCNT plan is not allowed for vehicle age '.$car_age. ' years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    // elseif($productData->product_identifier == 'ZDCET' && $fg_applicable_addons['ZDCET'] == 'N')
    // {
    //     return  [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'ZDCET plan is not allowed for vehicle age '.$car_age. ' years',
    //         'request' => [
    //             'message' => 'ZDCET plan is not allowed for vehicle age '.$car_age. ' years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    // elseif($productData->product_identifier == 'RSPBK' && $fg_applicable_addons['RSPBK'] == 'N')
    // {
    //     return  [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'RSPBK plan is not allowed for vehicle age '.$car_age. ' years',
    //         'request' => [
    //             'message' => 'RSPBK plan is not allowed for vehicle age '.$car_age. ' years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }

    $premium_type = $premium_type_array->premium_type_code;
    $policy_type = $premium_type_array->premium_type;

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



    try{
        $IsPos = 'N';
        if(config('IC.FUTURE_GENERALI.V1.CAR.REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){
            $is_FG_pos_disabled = 'Y';
        }
        else{
        $is_FG_pos_disabled = config('IC.FUTURE_GENERALI.V1.CAR.IS_FG_POS_DISABLED');
        }
        $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('IC.FUTURE_GENERALI.V1.CAR.IS_POS_ENABLED');
        $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('IC.FUTURE_GENERALI.V1.CAR.IS_POS_TESTING_MODE_ENABLE');
        //$is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
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
        $usedCar = 'N';


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
            $PolicyNo = $InsuredName = $PreviousPolExpDt = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date ='';
            $contract_type = 'F13';
            $risk_type = 'F13';
            $reg_no = str_replace('-', '', $requestData->rto_code.'-AB-1234');

            /* if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
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

            }
            elseif($pos_testing_mode === 'Y')
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                $contract_type = 'P13';
                $risk_type = 'F13';
            } */

        }
        else
        {
            $current_date = date('Y-m-d');
            $expdate = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $vehicle_in_30_days = get_date_diff('day', $current_date, $expdate);

            if ($requestData->business_type == 'rollover' &&  $vehicle_in_30_days > 30) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Future Policy Expiry date is allowed only upto 30 days',
                    'request' => [
                        'car_age' => $car_age,
                        'date_duration_in_days' => $vehicle_in_30_days,
                        'message' => 'Future Policy Expiry date is allowed only upto 30 days',
                    ]
                ];
            }
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));

            }

            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = $requestData->is_claim;
            $ncb_declaration = 'N';
            $NewCar = 'N';
            $rollover = 'Y';
            $today_date = date('Y-m-d');

            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                if(in_array($premium_type_array->premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            } else {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                if(in_array($premium_type_array->premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            }

            if($requestData->previous_policy_type == 'Not sure')
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                $usedCar = 'Y';
                $rollover = 'N';
                if(in_array($premium_type_array->premium_type_code, ['third_party_breakin'])) {
                    $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                }
            }

            if($requestData->ownership_changed == 'Y')
            {
                $ncb_declaration = 'N';
                $motor_no_claim_bonus = $requestData->previous_ncb = 0;
                $motor_applicable_ncb = $requestData->applicable_ncb = 0;
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
            }

            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $PolicyNo = '1234567';
            $InsuredName = 'Bharti Axa General Insurance Co. Ltd.';
            $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
            $ClientCode = '43207086';
            $Address1 = 'ABC';
            $Address2 = 'PQR';
            $tp_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year', strtotime($requestData->previous_policy_expiry_date)))));
            $tp_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
            $reg_no = isset($requestData->vehicle_registration_no) ? str_replace("-", "", $requestData->vehicle_registration_no) : '';

            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

            if($date_diff > 90)
            {
               $motor_expired_more_than_90_days = 'Y';
            }
            else
            {
                $motor_expired_more_than_90_days = 'N';

            }


            if ($claimMadeinPreviousPolicy == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party') {

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
               $contract_type = 'FVO';
               $risk_type = 'FVO';

            }
            else
            {
                $contract_type = 'FPV';
                $risk_type = 'FPV';
            }

            if($requestData->ownership_changed == 'Y')
            {
                $ncb_declaration = 'N';
            }

            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
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

        if(config('IC.FUTURE_GENERALI.V1.CAR.IS_POS_TESTING_MODE_ENABLE') == 'Y')
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

        $UserProductJourney = UserProductJourney::where('user_product_journey_id', $enquiryId)->first();
        $corporate_service ='N';
        $EmailAddr = '';
        $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_year_data = isset($value['tenure']) ? $value['tenure'] : '1';
                    
                }
            }
        }
        if(!empty($UserProductJourney->corporate_id) && !empty($UserProductJourney->domain_id) && config('IC.FUTURE_GENERALI.V1.CAR.IS_ENABLED_AFFINITY') == 'Y')
        {
                $corporate_service = 'Y';
                $EmailAddr =  $UserProductJourney->user_email;
        }

        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage")
        {
            $CPAReq = 'Y';
            $cpa_nom_name = 'Legal Hair';
            $cpa_nom_age = '21';
            $cpa_nom_age_det = 'Y';
            $cpa_nom_perc = '100';
            $cpa_relation = 'SPOU';
            $cpa_appointee_name = '';
            $cpa_appointe_rel = '';
            if ($requestData->business_type == 'newbusiness')
            {
                $cpa_year = isset($cpa_year_data) ? $cpa_year_data : '3';
                // $cpa_year = '1'; // By Default CPA will be 1 year
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

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
        $addon = [];
        $product_identifier = '';



        if ($productData->zero_dep == '0')
        {

                //zero dep  7 year
                /*if ($car_age <=7 && $productData->product_identifier=='zero_dep') {
                    $product_identifier = 'STZDP';
                    $addon[] = [
                    'CoverCode' => 'STZDP',
                    ];
                }

                //zero dep plus consumable 7 year
                if ($car_age <=7 && $productData->product_identifier=='zero dep plus consumable') {
                    $product_identifier = 'ZDCNS';
                $addon[] = [
                'CoverCode' => 'ZDCNS',
                ];
            }
                //zero dep plus consumable plus engine 7 year
                if ($car_age <=7 && $productData->product_identifier=='zero dep plus consumable plus engine') {
                    $product_identifier = 'ZDCNE';
                $addon[] = [
                    'CoverCode' => 'ZDCNE',
                ];
            }
                //zero dep plus consumable plus tyre 5 year
                if ($car_age <=5 && $productData->product_identifier=='zero dep plus consumable plus tyre') {
                    $product_identifier = 'ZDCNT';
                $addon[] = [
                    'CoverCode' => 'ZDCNT',
                ];
            }
                //zero dep plus consumable plus engine plus tyre 5 year
                if ($car_age <=5 && $productData->product_identifier=='zero dep plus consumable plus engine plus tyre') {
                    $product_identifier = 'ZDCET';
                $addon[] = [
                    'CoverCode' => 'ZDCET',
                ];
            }

                //zero dep plus consumable plus engine plus tyre plus RTI 3 year
                if ($car_age <=3 && $productData->product_identifier=='zero dep plus consumable plus engine plus tyre plus RTI') {
                    $product_identifier = 'ZCETR';
                $addon[] = [
                    'CoverCode' => 'ZCETR',
                ];
            }*/
            if($productData->product_identifier == 'STZDP' /*&& $fg_applicable_addons['STZDP'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'STZDP',
                ];
            }
            elseif($productData->product_identifier == 'ZDCNS' /*&& $fg_applicable_addons['ZDCNS'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'ZDCNS',
                ];
            }
            elseif($productData->product_identifier == 'ZDCNE' /*&& $fg_applicable_addons['ZDCNE'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'ZDCNE',
                ];
            }
            elseif($productData->product_identifier == 'ZDCNT' /*&& $fg_applicable_addons['ZDCNT'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'ZDCNT',
                ];
            }
            elseif($productData->product_identifier == 'ZDCET' /*&& $fg_applicable_addons['ZDCET'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'ZDCET',
                ];
            }
            elseif($productData->product_identifier == 'ZCETR' /*&& $fg_applicable_addons['ZCETR'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'ZCETR',
                ];
            }

        }
        else
        {
             //standlone rsa  7 year
          /*if ($car_age <=7 && $productData->product_identifier == 'Basic_with_Addons' ) {
            $product_identifier = 'Basic with Addons';
            $addon[] = [
               'CoverCode' => 'STRSA',
            ];
            if($car_age <=5)
            {
                $addon[] = [
                    'CoverCode' => 'STNCB',
                ];
            }*/
            if($fg_applicable_addons['STNCB'] == 'Y')
            {
                $addon[] = [
                    'CoverCode' => 'STNCB',
                ];
            }

            if($productData->product_identifier == 'RSPBK' /* && $fg_applicable_addons['RSPBK'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'RSPBK',
                ];
            }
            elseif($productData->product_identifier == 'basic' /* && $fg_applicable_addons['STRSA'] == 'Y' */)
            {
                $addon[] = [
                    'CoverCode' => 'STRSA',
                ];
            }

        }

       /*if ($car_age <=5 && $productData->product_identifier == 'STNCB') {
        $product_identifier = 'STNCB';
        $addon[] = [
            'CoverCode' => 'STNCB',
        ];
        }
         //rsa plus personal belonging plus key loss 7 year
         if ($car_age <=7 && $productData->product_identifier=='rsa plus personal belonging plus key loss') {
            $product_identifier = 'RSPBK';
        $addon[] = [
            'CoverCode' => 'RSPBK',
        ];
    }

    if($productData->product_identifier == 'without_addon')
    {
        $product_identifier = 'Basic';
        $addon = [];
    }*/


         //standlone NCB Protection  5 year
    //      if ($car_age <=5 ) {
    //      $addon[] = [
    //         'CoverCode' => 'STNCB',
    //     ];
    // }

       // }
        if($premium_type == 'third_party' /* || $car_age > 7*/)
        {
            $addon = [];
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
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
                   if($value->name == 'anti-theft device')
                   {
                        $IsAntiTheftDiscount = 'true';
                   }
                }
            }

        $idv = 0;




        switch($premium_type)
        {
            case "comprehensive":
            $cover_type = "CO";
            break;
            case "own_damage":
            $cover_type = "OD";

            break;
            case "third_party":
            $cover_type = "LO";
            break;

        }

        if($corporate_service !== 'Y')
        {
            $VendorCode = config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE');
            $VendorUserId =config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE') ;
            $AgentCode = config('IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE') ;
            $BranchCode = ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE');
        }else
        {
            $VendorCode = config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE_CORPORATE');
            $VendorUserId =config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE_CORPORATE') ;
            $AgentCode = config('IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE_CORPORATE') ;
            $BranchCode = ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE_CORPORATE');
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
            'Uid'          => time().rand(10000, 99999), //date('Ymshis').rand(0,9),
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
                'Salutation'    => '',
                'FirstName'     => '',
                'LastName'      => '',
                'DOB'           => '',
                'Gender'        => '',
                'MaritalStatus' => '',
                'Occupation'    => 'OTHR',
                'PANNo'         => '',
                'GSTIN'         => '',
                'AadharNo'      => '',
                'EIANo'         => '',
                'CKYCNo'        => '',
                'CKYCRefNo'     => '',

                'Address1'      => [
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
                    'EmailAddr'   => $EmailAddr,
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
                    'EmailAddr'   => '',
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
                    'RTOCode'                 => $rto_data->rta_code,//str_replace('-', '', $requestData->rto_code),
                    'Make'                    => $mmv_data->make,
                    'ModelCode'               => $mmv_data->vehicle_code,
                    'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : (!empty($reg_no) ? strtoupper($reg_no) : str_replace('-', '', $rto_data->rta_code.'-AB-1234')),
                    'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'ManufacturingYear'       => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'FuelType'                => $fuelType,
                    'CNGOrLPG'                => [
                        'InbuiltKit'    =>  in_array($fuelType, ['C', 'L']) ? 'Y' : 'N',
                        'IVDOfCNGOrLPG' => $bifuel == 'true' ? $BiFuelKitSi : '',
                    ],
                    'BodyType'                => 'SOLO',
                    'EngineNo'                => 'TESTENGINEE123456',
                    'ChassiNo'                => 'TESTCHASSIS8767894',
                    'CubicCapacity'           => $mmv_data->cc,
                    'SeatingCapacity'         => $mmv_data->seating_capacity,
                    'IDV'                     => ceil($idv),
                    'GrossWeigh'              => '',
                    'CarriageCapacityFlag'    => '',
                    'ValidPUC'                => 'Y',
                    'TrailerTowedBy'          => '',
                    'TrailerRegNo'            => '',
                    'NoOfTrailer'             => '',
                    'TrailerValLimPaxIDVDays' => '',
                ],
                'InterestParty'     => [
                    'Code'     => '',
                    'BankName' => '',
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
                'AddonReq'          =>(config('IC.FUTURE_GENERALI.V1.CAR.IS_ADDON_ENABLE')=='N' ? 'N': (($premium_type == 'third_party' || $addon==null )? 'N' : 'Y')), // FOR ZERO DEP value is Y and COVER CODE is PLAN1
                'Addon'             =>(((config('IC.FUTURE_GENERALI.V1.CAR.IS_ADDON_ENABLE')=='N') || empty($addon)) ? ['CoverCode' => ''] : $addon ),
                'PreviousTPInsDtls' =>[
                            'PreviousInsurer' => ($premium_type == "own_damage") ? 'RG':'',
                            'TPPolicyNumber' => ($premium_type== "own_damage") ? 'RG874592': '',
                            'TPPolicyEffdate' => ($premium_type == "own_damage") ? $tp_start_date :'',
                            'TPPolicyExpiryDate' => ($premium_type == "own_damage") ? $tp_end_date :''

                            ],
                'PreviousInsDtls'   => [
                    'UsedCar'        => $usedCar,
                    'UsedCarList'    => [
                        'PurchaseDate'    => ($usedCar == 'Y') ? date('d/m/Y', strtotime($requestData->vehicle_register_date)) : '' ,
                        'InspectionRptNo' => '',
                        'InspectionDt'    => '',
                    ],
                    'RollOver'       => $rollover,
                    'RollOverList'   => [
                        'PolicyNo'              => ($rollover == 'N') ? '' :'1234567',
                        'InsuredName'           => ($rollover == 'N') ? '' :$InsuredName,
                        'PreviousPolExpDt'      => ($rollover == 'N') ? '' :$PreviousPolExpDt,
                        'ClientCode'            => ($rollover == 'N') ? '' :$ClientCode,
                        'Address1'              => ($rollover == 'N') ? '' :$Address1,
                        'Address2'              => ($rollover == 'N') ? '' :$Address2,
                        'Address3'              => '',
                        'Address4'              => '',
                        'Address5'              => '',
                        'PinCode'               => ($rollover == 'N') ? '' :'400001',
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

        $data = $quote_array;
        unset($data['Uid']);
        $checksum_data = checksum_encrypt($data);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'future_generali',$checksum_data,'CAR');
        $additional_data = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'CreatePolicy',
            'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
            'method' => 'Quote Generation',
            'section' => 'car',
            'checksum' => $checksum_data,
            'transaction_type' => 'quote',
            'productName'  => $productData->product_name
        ];

        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
            $data = $is_data_exits_for_checksum;
        }else{
            $data = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
        }

        if ($data['response'])
        {
            $quote_output = html_entity_decode($data['response']);
            $quote_output = XmlToArray::convert($quote_output);

            $skip_second_call = false;
            if (isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy']))
            {
                $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];

                if ($quote_output['Status'] == 'Fail') {
                    if (isset($quote_output['Error'])) {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                        ];
                    } elseif (isset($quote_output['ErrorMessage'])) {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                        ];
                    } elseif (isset($quote_output['ValidationError'])) {

                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                        ];
                    }
                }else{
                    update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success" );

                    if (isset($quote_output['VehicleIDV'])) {
                        $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                    }

                    if($premium_type != 'third_party')
                    {
                        $idv = round((float) $quote_output['VehicleIDV']);
                        $min_idv = ceil($idv * 0.8); // changes 10/5 to 20/20 % 
                        $max_idv = floor($idv * 1.2);
                    }
                    else
                    {
                        $idv = $min_idv = $max_idv = 0;
                    }


                    // idv change condition
                    if ($requestData->is_idv_changed == 'Y') {
                        if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                            $idv = floor($max_idv);
                        } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                            $idv = ceil($min_idv);
                        } else {
                            $idv = $requestData->edit_idv;
                        }
                    }else{
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $quote_output['VehicleIDV'] = $idv;
                                $skip_second_call = true;
                                $idv = $idv;
                                break;
                            case 'min_idv':
                                $quote_output['VehicleIDV'] = $min_idv;
                                $idv = $min_idv;
                                break;
                            case 'max_idv':
                                $quote_output['VehicleIDV'] = $max_idv;
                                $idv = $max_idv;
                                break;
                            default:
                            $quote_output['VehicleIDV'] = $idv;
                                $idv = 0;
                                break;
                        }
                        // $idv = 0;#$min_idv;bydefault 0 idv quotes for package addon
                    }

                    $quote_array['Risk']['Vehicle']['IDV'] = ceil($idv);
                    $quote_array['Uid'] = time().rand(10000, 99999);
                    $additional_data = [
                        'requestMethod' => 'post',
                        'enquiryId' => $enquiryId,
                        'soap_action' => 'CreatePolicy',
                        'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
                        'method' => 'Quote Generation - IDV changed',
                        'section' => 'car',
                        'transaction_type' => 'quote',
                        'productName'  => $productData->product_name
                    ];

                    if (config('IC.FUTURE_GENERALI.V1.CAR.REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $idv>= 5000000)
                    {
                        $quote_array ['POS_MISP'] = [
                            'Type'  => '',
                            'PanNo' => '',
                        ];
                    } elseif(!empty($pos_data))
                    {
                        $quote_array ['POS_MISP']  = [
                            'Type'  => ($IsPos == 'Y') ? 'P' : '',
                            'PanNo' => $pos_data->pan_no,
                        ];
                    }
                    if(!$skip_second_call) {
                        $data = $quote_array;
                        unset($data['Uid']);
                        $checksum_data = checksum_encrypt($data);
                        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'future_generali',$checksum_data,'CAR');
                        $additional_data['checksum'] = $checksum_data;

                        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                            $data = $is_data_exits_for_checksum;
                        }else{
                            $data = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
                        }
                    }

                    if ($data['response']) {
                        $quote_output = html_entity_decode($data['response']);
                        $quote_output = XmlToArray::convert($quote_output);
                        // print_r(json_encode([$quote_array,$quote_output]));die;
                        if (isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy']))
                        {
                            $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];

                            if ($quote_output['Status'] == 'Fail') {
                                if (isset($quote_output['Error'])) {
                                    return [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                        'premium_amount' => 0,
                                        'status'         => false,
                                        'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                                    ];
                                } elseif (isset($quote_output['ErrorMessage'])) {
                                    return [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                        'premium_amount' => 0,
                                        'status'         => false,
                                        'message'        => $quote_output['ErrorMessage']
                                    ];
                                } elseif (isset($quote_output['ValidationError'])) {

                                    return [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                        'premium_amount' => 0,
                                        'status'         => false,
                                        'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                                    ];
                                }
                            }
                        }
                        else
                        {

                            $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult'];

                            if(isset($quote_output['ValidationError']))
                            {
                                return $return_data = [
                                                'webservice_id' => $data['webservice_id'],
                                                'table' => $data['table'],
                                                'premium_amount' => 0,
                                                'status'         => false,
                                                'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                                              ];
                            }
                            elseif(isset($quote_output['ErrorMessage']))
                            {
                                return $return_data = [
                                                'webservice_id' => $data['webservice_id'],
                                                'table' => $data['table'],
                                                'premium_amount' => 0,
                                                'status'         => false,
                                                'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                                              ];

                            }
                            elseif (isset($quote_output['Error']))
                            {
                                return $return_data = [
                                                    'webservice_id' => $data['webservice_id'],
                                                    'table' => $data['table'],
                                                   'premium_amount' => 0,
                                                   'status'         => false,
                                                   'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                                                   ];
                            }
                        }

                    }

                    if (isset($quote_output['VehicleIDV'])) {
                        $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                    }

                    $total_idv = ($premium_type == 'third_party') ? 0 : round($quote_output['VehicleIDV'] ?? 0);
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
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;
                    $selected_addons_data = [
                        'in_built'   => [],
                        'additional' => [],
                        'other_premium' => 0
                    ];
                    $addncbProtectionInAddons = false;
                    $ncbProptectionValue = 0;
                    $applicable_addons = [];
                    $newselected_addons_data=[];
                    $legal_liability_to_employee = 0;

                    //$applicable_addons=["roadSideAssistance", "ncbProtection", "tyreSecure", "zeroDepreciation", "engineProtector", "consumables", "keyReplace","returnToInvoice","lopb"];
                    foreach ($quote_output['NewDataSet']['Table1'] as $key => $cover) {

                        $cover = array_map('trim', $cover);
                        $value = ($cover['BOValue']);

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
                        }  elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                            $ncb_discount = abs($value);
                        } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                            $discount_amount = round(str_replace('-', '', $value));
                        }  elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                            $discperc = $value;
                        }
                        elseif(($cover['Code'] == 'LLEE' && $cover['Type'] == 'TP'))
                        {
                            $legal_liability_to_employee = $value;
                        }
                        elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD')) {

                            if((int)$value == 0)
                            {

                                return [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'ZDCNS Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                                ];
                            }

                            $selected_addons_data = [
                                'in_built'   => [
                                    'keyReplace' => 0,
                                    'lopb' => 0,
                                    'consumables'=>0,
                                    'zero_depreciation' =>  $value,
                                    'roadSideAssistance' => 0,
                                ],
                                'additional' => [ ],
                                 'other_premium' => 0
                            ];
                            array_push($applicable_addons,'zeroDepreciation');
                            array_push($applicable_addons, 'consumables');
                            array_push($applicable_addons, 'roadSideAssistance');
                            array_push($applicable_addons, 'keyReplace');
                            array_push($applicable_addons, 'lopb');

                        }  elseif (($cover['Code'] == 'ZDCNE') && ($cover['Type'] == 'OD')) {
                            if((int)$value == 0)
                            {

                                return [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'ZDCNE Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                                ];
                            }
                            $selected_addons_data = [
                                'in_built'   => [
                                    'keyReplace' => 0,
                                    'lopb' => 0,
                                    'consumables'=>0,
                                    'engineProtector'=>0,
                                    'zero_depreciation' =>  $value,
                                    'roadSideAssistance' => 0,
                                ],
                                'additional' => [ ],
                                 'other_premium' => 0
                            ];
                            array_push($applicable_addons, 'zeroDepreciation');
                            array_push($applicable_addons, 'consumables');
                            array_push($applicable_addons, 'engineProtector');
                            array_push($applicable_addons, 'roadSideAssistance');
                            array_push($applicable_addons, 'keyReplace');
                            array_push($applicable_addons, 'lopb');
                        }  elseif (($cover['Code'] == 'ZDCNT') && ($cover['Type'] == 'OD')) {
                            if((int)$value == 0)
                            {

                                return [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'ZDCNT Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                                ];
                             }
                            $selected_addons_data = [
                                'in_built'   => [
                                    'keyReplace' => 0,
                                    'lopb' => 0,
                                    'consumables'=>0,
                                    'tyreSecure'=>0,
                                    'zero_depreciation' => $value,
                                    'roadSideAssistance' => 0,
                                ],
                                'additional' => [ ],
                                 'other_premium' => 0
                            ];
                            array_push($applicable_addons, 'zeroDepreciation');
                            array_push($applicable_addons, 'consumables');
                            array_push($applicable_addons, 'tyreSecure');
                            array_push($applicable_addons, 'roadSideAssistance');
                            array_push($applicable_addons, 'keyReplace');
                            array_push($applicable_addons, 'lopb');
                        } elseif (($cover['Code'] == 'ZDCET') && ($cover['Type'] == 'OD')) {
                        if((int)$value == 0)
                        {

                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'ZDCET Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                            $selected_addons_data = [
                                'in_built'   => [
                                    'keyReplace' => 0,
                                    'lopb' => 0,
                                    'consumables'=>0,
                                    'tyreSecure'=>0,
                                    'engineProtector'=>0,
                                    'zero_depreciation' =>  $value,
                                    'roadSideAssistance' => 0,
                                ],
                                'additional' => [ ],
                                 'other_premium' => 0
                            ];
                            array_push($applicable_addons, 'zeroDepreciation');
                            array_push($applicable_addons, 'consumables');
                            array_push($applicable_addons, 'engineProtector');
                            array_push($applicable_addons, 'tyreSecure');
                            array_push($applicable_addons, 'roadSideAssistance');
                            array_push($applicable_addons, 'keyReplace');
                            array_push($applicable_addons, 'lopb');
                        } elseif (($cover['Code'] == 'ZCETR') && ($cover['Type'] == 'OD')) {
                            if((int)$value == 0)
                            {

                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'ZCETR Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                            $selected_addons_data = [
                                'in_built'   => [
                                    'keyReplace' => 0,
                                    'lopb' => 0,
                                    'consumables'=>0,
                                    'tyreSecure'=>0,
                                    'engineProtector'=>0,
                                    'returnToInvoice'=>0,
                                    'zero_depreciation' => $value,
                                    'roadSideAssistance' => 0,
                                ],
                                'additional' => [ ],
                                 'other_premium' => 0
                            ];
                            array_push($applicable_addons, 'zeroDepreciation');
                            array_push($applicable_addons, 'consumables');
                            array_push($applicable_addons, 'engineProtector');
                            array_push($applicable_addons, 'tyreSecure');
                            array_push($applicable_addons, 'returnToInvoice');
                            array_push($applicable_addons, 'roadSideAssistance');
                            array_push($applicable_addons, 'keyReplace');
                            array_push($applicable_addons, 'lopb');
                        }
                        elseif (($cover['Code'] == 'STRSA') && ($cover['Type'] == 'OD')) {
                            if((int)$value == 0)
                            {

                                /*return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'STRSA Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];*/
                            $selected_addons_data = [
                                    'in_built'   => [],
                                    'additional' => [
                                        'roadSideAssistance' => 0,
                                    ],
                                    'other_premium' => 0
                                ];

                            }
                            else
                            {
                                $selected_addons_data = [
                                    'in_built'   => [],
                                    'additional' => [
                                        'roadSideAssistance' => (int)$value,
                                    ],
                                    'other_premium' => 0
                                ];
                                array_push($applicable_addons, 'roadSideAssistance');
                            }

                        }
                        elseif (($cover['Code'] == 'RSPBK') && ($cover['Type'] == 'OD')) {
                            if((int)$value == 0)
                            {

                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'RSPBK Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                            $selected_addons_data = [
                                'in_built'   => [
                                    'keyReplace' => 0,
                                    'lopb' => 0,
                                    'roadSideAssistance' =>(int)$value,
                                ],
                                'additional' => [],
                                 'other_premium' => 0
                            ];

                            array_push($applicable_addons, 'roadSideAssistance');
                            array_push($applicable_addons, 'keyReplace');
                            array_push($applicable_addons, 'lopb');
                        }   elseif (($cover['Code'] == 'STZDP') && ($cover['Type'] == 'OD')) {
                            if((int)$value == 0)
                            {

                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'STZDP Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                            $selected_addons_data = [
                                'in_built'   => [
                                    'zero_depreciation' => $value,
                                    ],
                                'additional' => [

                                ],
                                 'other_premium' => 0
                            ];
                            array_push($applicable_addons, 'zeroDepreciation');
                            // array_push($applicable_addons, 'roadSideAssistance');
                            // array_push($applicable_addons, 'keyReplace');
                            // array_push($applicable_addons, 'lopb');
                        }
                        if (($cover['Code'] == 'STNCB') && ($cover['Type'] == 'OD')) {
                            if((int)$value == 0)
                            {

                            /*return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'STNCB Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];*/
                        }
                        else
                        {
                                $addncbProtectionInAddons = true;
                                $ncbProptectionValue = (int)$value;
                                array_push($applicable_addons, 'ncbProtection');
                        }

                        }

                    }

                    if($addncbProtectionInAddons)
                    {
                        $selected_addons_data['additional']['ncbProtection'] = (int)$ncbProptectionValue;
                    }


                    if(isset($selected_addons_data['in_built']) && $selected_addons_data['in_built']!=null){
                        $newselected_addons_data=array_merge($newselected_addons_data,$selected_addons_data['in_built']);
                       }
                       if(isset($selected_addons_data['additional']) && $selected_addons_data['additional']!=null){
                        $newselected_addons_data=array_merge($newselected_addons_data,$selected_addons_data['additional']);
                       }
                        /* if(isset($selected_addons_data['other_premium']) && $selected_addons_data['other_premium']!=null){
                        $newselected_addons_data=array_merge($newselected_addons_data,$selected_addons_data['other_premium']);
                       } */
                    if ($discperc > 0) {
                        $od_premium = $od_premium + $discount_amount;
                        $discount_amount = 0;
                    }


                    $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                    $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount + $legal_liability_to_employee;
                    $total_discount = $ncb_discount + $discount_amount;
                    $basePremium = $total_od + $total_tp - $total_discount;
                    $total_addons = $zero_dep_amount;

                    $final_tp = $total_tp + $pa_owner ;

                    $od_base_premium = $total_od;

                    $total_premium_amount = $total_od_premium + $total_tp_premium + $total_addons;
                    $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));

                    $totalTax = $basePremium * 0.18;

                    $final_premium = $basePremium + $totalTax;

                    $policystartdatetime = DateTime::createFromFormat('d/m/Y', $policy_start_date);
                    $policy_start_date = $policystartdatetime->format('d-m-Y');

                    if($productData->zero_dep == '0')
                    {
                        if(isset($selected_addons_data['additional']['zero_depreciation']) && $selected_addons_data['additional']['zero_depreciation'] > 0)
                        {
                            $selected_addons_data['in_built']['zero_depreciation'] = $selected_addons_data['additional']['zero_depreciation'];
                            unset($selected_addons_data['additional']['zero_depreciation']);
                        }

                    }
                    $selected_addons_data['in_built_premium'] = array_sum($selected_addons_data['in_built']);
                    $selected_addons_data['additional_premium'] = array_sum($selected_addons_data['additional']);
                    foreach($selected_addons_data['additional'] as $k=>$v){
                        if($v == 0){
                            unset($selected_addons_data['additional'][$k]);
                        }
                    }
                    //$applicable_addons=["roadSideAssistance", "ncbProtection", "tyreSecure", "zeroDepreciation", "engineProtector", "consumables", "keyReplace","returnToInvoice","lopb"];
                     /*if ($car_age > 7) {
                         array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                         array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                         array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                         array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                         array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                         array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                       }

                     if ($car_age > 5) {
                         array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                         array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                     }

                     if ($car_age > 3) {
                         array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                     }*/

                     $other_addons = [
                        'LegalLiabilityToEmployee' => $legal_liability_to_employee ?? 0
                     ];
                     if(empty($legal_liability_to_employee))
                     {
                        unset($other_addons['LegalLiabilityToEmployee']);
                     }
                    $data_response = [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'idv' => $premium_type == 'third_party' ? 0 : round($total_idv),
                            'vehicle_idv' => $total_idv,
                            'min_idv' => $min_idv,
                            'max_idv' => $max_idv,
                            'rto_decline' => NULL,
                            'rto_decline_number' => NULL,
                            'mmv_decline' => NULL,
                            'mmv_decline_name' => NULL,
                            'policy_type' => $premium_type == 'third_party' ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => '',
                            'vehicle_registration_no' => $requestData->rto_code,
                            'rto_no' => $requestData->rto_code,
                            'voluntary_excess' => $requestData->voluntary_excess_value,
                            'version_id' => $mmv_data->ic_version_code,
                            'showroom_price' => 0,
                            'fuel_type' => $requestData->fuel_type,
                            'ncb_discount' => $motor_applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_name' => $productData->product_sub_type_name . ' - ' .   $productData->product_identifier,
                            'mmv_detail' => $mmv_data,
                            'master_policy_id' => [
                                'policy_id' => $productData->policy_id,
                                'policy_no' => $productData->policy_no,
                                'policy_start_date' => '',
                                'policy_end_date' =>   '',
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
                                'is_payment_online' => $productData->is_payment_online
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
                                'ic_vehicle_discount' =>  round($discount_amount),
                            ],
                            'basic_premium' => round($od_premium),
                            'deduction_of_ncb' => round($ncb_discount),
                            'tppd_premium_amount' => round($tp_premium),
                            'motor_electric_accessories_value' =>round($electrical_amount),
                            'motor_non_electric_accessories_value' => round($non_electrical_amount),
                            'cover_unnamed_passenger_value' => round($pa_unnamed),
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'default_paid_driver' => $liability,
                            'motor_additional_paid_driver' => 0,
                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                            'compulsory_pa_own_driver' => $pa_owner,
                            'total_accessories_amount(net_od_premium)' => 0,
                            'total_own_damage' =>  round($total_od),
                            'total_liability_premium' => round($total_tp),
                            'net_premium' => round($basePremium),
                            'service_tax_amount' => 0,
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'voluntary_excess' => 0,
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
                            'business_type' => ($requestData->business_type =='newbusiness') ? 'New Business' : (($requestData->business_type == "breakin" || ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party')) ? 'Breakin' : 'Roll over'),
                            'service_err_code' => NULL,
                            'service_err_msg' => NULL,
                            'policyStartDate' => $policy_start_date,
                            'policyEndDate' => $policy_end_date,
                            'ic_of' => $productData->company_id,
                            'ic_vehicle_discount' => round($discount_amount),
                            'vehicle_in_90_days' => 0,
                            'get_policy_expiry_date' => NULL,
                            'get_changed_discount_quoteid' => 0,
                            'vehicle_discount_detail' => [
                                'discount_id' => NULL,
                                'discount_rate' => NULL
                            ],
                            'other_covers' => $other_addons,
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online,
                            'policy_id' => $productData->policy_id,
                            'insurane_company_id' => $productData->company_id,
                            "max_addons_selection" => NULL,
                            'add_ons_data' => $selected_addons_data,
                            'tppd_discount' => 0,
                            'applicable_addons' => $applicable_addons,
                            'isInspectionApplicable' => $isInspectionApplicable
                        ]
                    ];
                    if($lpg_cng_tp_amount || $lpg_cng_amount)
                    {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = round($lpg_cng_amount);
                        $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp_amount;
                    }
                    if(!empty($cpa_year_data) && $requestData->business_type == 'newbusiness' && $cpa_year_data == '3')
                    {
                       
                        $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
                    }
                    if (!empty($legal_liability_to_employee)) {
                        $data_response['Data']['LegalLiabilityToEmployee'] = $legal_liability_to_employee ?? 0;
                    }

                    return camelCase($data_response);

                }
            }
            else
            {

                $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult'];

                if(isset($quote_output['ValidationError']))
                {
                    return $return_data = [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'premium_amount' => 0,
                                    'status'         => false,
                                    'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                                  ];
                }
                elseif(isset($quote_output['ErrorMessage']))
                {
                    return $return_data = [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'premium_amount' => 0,
                                    'status'         => false,
                                    'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                                  ];

                }
                elseif (isset($quote_output['Error']))
                {
                    return $return_data = [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                       'premium_amount' => 0,
                                       'status'         => false,
                                       'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                                       ];
                } elseif (isset($quote_output['Root']['Error']) && !empty($quote_output['Root']['Error'])) {
                    return $return_data = [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status'         => false,
                        'message'        => $quote_output['Root']['Error']
                    ];
                }
                else
                {
                     return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => 'Car Insurer Not found'
                      ];
                }
            }

        }
        else
        {
             return [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Car Insurer Not found'
          ];
        }

    }catch (\Exception $e) {
       return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Car Insurer Not found' . $e->getMessage() . ' line ' . $e->getLine()
        ];
    }

}
