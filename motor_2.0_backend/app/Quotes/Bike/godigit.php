<?php
if (config("IC.GODIGIT.BIKE.CVWEBSERVICEHELPER") == "Y") { 
    include_once app_path().'/Helpers/CvWebServiceHelper.php';
} else {
    include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
}

use Carbon\Carbon;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use App\Models\RenewalDataApi;
use Illuminate\Support\Facades\DB;

function getQuote($enquiryId, $requestData, $productData)
{
    // owenership_change case is already handled in GODIGIT,  inspection required for owenership_change case

    //return $requestData->rto_code;die;
    //$refer_webservice = config('ENABLE_TO_GET_DATA_FROM_WEBSERVICE_GODIGIT_BIKE') == 'Y';
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if(empty($requestData->rto_code))
    {
        return  [  'premium_amount' => 0,
                    'status' => false,
                    'message' => 'RTO not available',
                    'request'=> [
                        'rto_code'=> $requestData->rto_code
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
   $mmv_data = get_mmv_details($productData,$requestData->version_id,$productData->company_alias);
   if($mmv_data['status'] == 1)
   {
     $mmv_data = $mmv_data['data'];
   }
   else
   {
       return  [
           'premium_amount' => 0,
           'status' => false,
           'message' => $mmv_data['message'],
           'request'=> $mmv_data
       ];
   }
   //dd($mmv_data->ic_version_code);
   $mmv = (object) array_change_key_case((array) $mmv_data,CASE_LOWER);
    if(empty($mmv->ic_version_code) || $mmv->ic_version_code == '')
    {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];        
    }
    else if($mmv->ic_version_code == 'DNE')
    {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];        
    }
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = floor($age / 12);
    $mmv_data = 
    [   'manf_name' => $mmv->make_name,
        'model_name' => $mmv->model_name,
        'version_name' => $mmv->variant_name,
        'seating_capacity' => $mmv->seating_capacity,
        'carrying_capacity' => $mmv->seating_capacity - 1,
        'cubic_capacity' => $mmv->cubic_capacity,
        'fuel_type' =>  $mmv->fuel_type,
        'gross_vehicle_weight' => $mmv->vehicle_weight ?? NULL,
        'vehicle_type' => ($mmv->vehicle_type),
        'version_id' => $mmv->ic_version_code,
        'kw' => $mmv->cubic_capacity,
    ];

    $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();

    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    if (($interval->y >= 40) && ($tp_check == 'true')){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 40 years',
        ];
    }                    
//    if($premium_type =='own_damage_breakin')
//    {
//        return 
//        [
//            'premium_amount' => 0,
//            'status' => false,
//            'message' => "SAOD Break-in not allowed" // #10069 as Per the GIT ID, SAOD Breakin is Allowed 
//        ];
//
//    }
    if($premium_type =='third_party_breakin'){
        $premium_type ='third_party';
    }
    if($premium_type =='own_damage_breakin'){
        $premium_type ='own_damage';
    }
    if($premium_type == 'third_party') 
    {
        $policy_type = 'Third Party';
        $insurance_product_code = '20202';
        $previousNoClaimBonus = 'ZERO';
    }elseif($premium_type == 'own_damage')
    {
        $policy_type = 'Own Damage';
        $insurance_product_code = '20203';
        $ncb_percent = $requestData->previous_ncb;
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
        $previousNoClaimBonus = $no_claim_bonus[$ncb_percent] ?? 'ZERO';
    }
    else
    {
        $policy_type = 'Comprehensive';
        $insurance_product_code = '20201';
        $ncb_percent = $requestData->previous_ncb;
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
        $previousNoClaimBonus = $no_claim_bonus[$ncb_percent] ?? 'ZERO';
    }

    $voluntary_deductible = [
        '0' => 'ZERO',
        '1000' => 'THOUSAND',
        '2000' => 'TWO_THOUSAND',
        '2500' => 'TWENTYFIVE_HUNDRED',
        '3000' => 'THREE_THOUSAND'
    ];

    $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
    $motor_manf_year = $motor_manf_year_arr[1];
    $motor_manf_date = '01-'.$requestData->manufacture_year;
    $current_date = Carbon::now()->format('Y-m-d');
    $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
    $sub_insurance_product_code = 'PB';
    $is_vehicle_new = 'false';
    $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
    switch ($requestData->business_type) {

        case 'rollover':
            $is_vehicle_new = 'false';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $sub_insurance_product_code = 'PB';
            $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            break;

        case 'newbusiness':
            $is_vehicle_new = 'true';
            $policy_start_date = Carbon::today()->format('Y-m-d');
            $sub_insurance_product_code = '51';
            $previousNoClaimBonus = 'ZERO';
            if($requestData->vehicle_registration_no == 'NEW')
            {
                $vehicle_registration_no  = str_replace("-", "", godigitRtoCode($requestData->rto_code)) . "-NEW";
            }
            else
            {
                $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            }
            break;

        case 'breakin':
            $is_vehicle_new = 'false';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $sub_insurance_product_code = 'PB';
            $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            $breakin_make_time = strtotime('18:00:00');
            /* if($breakin_make_time > time())
            {
               $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
            }
            else
            {
              $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));
            } */
            $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));//godigit new IRDA policy start date logic

            //Default RID of a SATP breakin policy will be T+24
            if ($premium_type == 'third_party') {
                $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
            }
            break;
    }

        // car age calculation
        // $date1 = new DateTime($requestData->vehicle_register_date);
        // $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        // $interval = $date1->diff($date2);
        // $age = (($interval->y * 12) + $interval->m) + 1;
        // $car_age = ceil($age / 12);
        $rsa='true';
        $consum = 'true';
        $engineProtector ='true';
        $returnToInvoice= 'true';
        $date_difference = get_date_diff('year', $requestData->vehicle_register_date);
        $applicable_addons = [
            'zeroDepreciation', 'roadSideAssistance','engineProtector','returnToInvoice','consumables'
        ];
        if ($interval->y >= 6) {
            array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
            $consum = 'false';
        }
        if ($interval->y >= 10) {
            array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
            $engineProtector = 'false';
        }
        if ($date_difference > 3) {
            array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            $returnToInvoice = 'false';
        }
        if($requestData->business_type == 'breakin'|| $requestData->previous_policy_type == 'Third-party')
        {
            array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
            array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
        }
        if($interval->y >= 19)
        {
            array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
            $rsa='false';
        }
        if ($interval->y >= 6 && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC')))) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed for vehicle age greater than 6 years'
            ];
        }
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    if ($requestData->business_type == 'newbusiness')
    {
        $requestData->vehicle_register_date = date('d-m-Y');
       // $policy_end_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime($policy_start_date)));
        $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
        if ($date_difference > 0)
        {  
            return [
                'status' => false,
                'message' => 'Please Select Current Date for New Business'
            ];
        }
    }
    if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
     
        $expdate=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
        $vehicle_in_90_days = $date_difference = get_date_diff('day', $expdate);

        if ($date_difference > 90) {  
            $previousNoClaimBonus = 'ZERO';
        }
    }

    // if($requestData->is_claim == 'Y')
    // {
    //    $previousNoClaimBonus = 'ZERO';
    // }

/*     if(isset($requestData->voluntary_excess_value) && !empty($requestData->voluntary_excess_value))
    {
        if ( ! isset($voluntary_deductible[$requestData->voluntary_excess_value]))
        {
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Selected voluntary discount value is not available.'
            ];
        }

        $voluntary_deductible_amount = $voluntary_deductible[$requestData->voluntary_excess_value];
    }
    else
    {
        $voluntary_deductible_amount = 'ZERO';
    } */
    $voluntary_deductible_amount = 'ZERO';
    $cng_lpg_amt = $non_electrical_amt = $electrical_amt = null;
    $is_tppd = false;
    $is_lpg_cng = false;
    if($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' )
    {
        $sub_insurance_product_code = 50;
    }
    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                    ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts','compulsory_personal_accident')
                                    ->first();
    if (!empty($additional['discounts'])) {
        foreach ($additional['discounts'] as $data) {
            if (!($premium_type == "own_damage") && $data['name'] == 'TPPD Cover') {
                $is_tppd = true;
            }
        }
    }
    $is_electrical_selected = false;
    $is_non_electrical_selected = false;
    if(!empty($additional['accessories']))
    {
        foreach ($additional['accessories'] as $key => $data) 
        {
            if($data['name'] == 'External Bi-Fuel Kit CNG/LPG')
            {
                $cng_lpg_amt = $data['sumInsured'];
                $is_lpg_cng = true;
            }

            if($data['name'] == 'Non-Electrical Accessories')
            {
                $non_electrical_amt = $data['sumInsured'];
                $is_non_electrical_selected = true;
            }

            if($data['name'] == 'Electrical Accessories')
            {
                $electrical_amt = $data['sumInsured'];
                $is_electrical_selected = true;
            }
        }
    }
    
    if(isset($cng_lpg_amt) && ($cng_lpg_amt < 10000 || $cng_lpg_amt > 80000))
    {
        return  [   'premium_amount' => 0,
                    'status' => false,
                    'message' => 'CNG/LPG Insured amount, min = Rs.10000 and max = Rs.80000',
                ];
    }

    if(isset($non_electrical_amt) && ($non_electrical_amt < 412 || $non_electrical_amt > 82423))
    {
        return  [   'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Non-Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                ];
    }

    if(isset($electrical_amt) && ($electrical_amt < 412 || $electrical_amt > 82423))
    {
        return  [   'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                ];
    }

    $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = null;
    $cleanerLL = false;
    $no_of_cleanerLL = NULL;
    $no_of_driverLL = 0;
    $paidDriverLL = "false";
    $is_geo_ext = false;
    if (!empty($additional['additional_covers'])) {
        foreach ($additional['additional_covers'] as $data) {
            if ($data['name'] == 'LL paid driver') {
                $no_of_driverLL = 1;
                $paidDriverLL = "true";
            }
            if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                $cover_pa_paid_driver = $data['sumInsured'];
            }

            if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                $cover_pa_unnamed_passenger = $data['sumInsured'];
            }

            if ($data['name'] == 'Geographical Extension') {
                $is_geo_ext = true;
                $countries = $data['countries'];
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

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
    $password = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');

    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if($pos_data) {

            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $pos_data->agent_id,
                'productSubTypeId' => 2,
                'ic_integration_type' => 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $webUserId = $credentials['data']['web_user_id'];
                $password = $credentials['data']['password'];
            }

            $is_pos = 'true';
            $posp_name = $pos_data->agent_name;
            $posp_unique_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
            $posp_pan_number = $pos_data->pan_no;
            $posp_aadhar_number = $pos_data->aadhar_no;
            $posp_contact_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
            $posp_location = $pos_data->region_name;
        }
        if($is_pos_testing_mode == 'Y')
        {
            $is_pos = 'true';
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = 'ABGTY8890Z';
            $posp_aadhar_number = '569278616999';
            $posp_contact_number = '9768574564';
        }
    }else if($is_pos_testing_mode == 'Y')
    {
        $is_pos = 'true';
        $posp_name = 'test';
        $posp_unique_number = '9768574564';
        $posp_pan_number = 'ABGTY8890Z';
        $posp_aadhar_number = '569278616999';
        $posp_contact_number = '9768574564';
    }
    if ($productData->product_identifier == 'zero_dep_double_claim') {
        $claims_covered = 'TWO';
        $zero_dep = 'true';
    } elseif ($productData->product_identifier == 'zero_dep_unlimited_claim') {
        $claims_covered = 'UNLIMITED';
        $zero_dep = 'true';
    } elseif ($productData->product_identifier == 'zero_dep') {
        $claims_covered = 'ONE';
        $zero_dep = 'true';
    } else {
        $claims_covered = NULL;
        $zero_dep = 'false';
    }
    if($requestData->previous_policy_type == 'Third-party')
    {
        $previousNoClaimBonus = 'ZERO';
    }
    if($requestData->business_type == 'breakin' || $requestData->previous_policy_type == 'Third-party')
    {
        $claims_covered = NULL;
        $zero_dep = 'false';
        $rsa = 'false';
        $engineProtector ='false';
        $consum = $returnToInvoice = 'false';
    }
    if($requestData->previous_policy_type != 'Not sure')
        {
            $isPreviousInsurerKnown = "true";
        }
        else
        {
            $isPreviousInsurerKnown = "false";
        }

        $tenure = null;
        if ($additional && $additional['compulsory_personal_accident'] != NULL && $additional['compulsory_personal_accident'] != '') {
            $addons = $additional['compulsory_personal_accident'];
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $tenure = isset($value['tenure']) ? 5 : 1;

                }
            }
        }

        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
        {
            if($requestData->business_type == 'newbusiness')
            {
                $tenure = isset($tenure) ? $tenure :5; 
            }
            else{
                $tenure = isset($tenure) ? $tenure :1;
            }
        }

    $userProposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
    $previousPolicyNumber = $userProposal->previous_policy_number ?? null;
    $premium_req_array = 
    [   'enquiryId' =>($premium_type == "own_damage") ? 'GODIGIT_QQ_TWO_WHEELER_SAOD_01': 'GODIGIT_QQ_TWO_WHEELER_PACKAGE_01',
        'contract' =>
        [   'insuranceProductCode' => $insurance_product_code,
            'subInsuranceProductCode' => $sub_insurance_product_code,
            'startDate' => $policy_start_date,
            'endDate' => $policy_end_date,
            'policyHolderType' => $policy_holder_type,
            'externalPolicyNumber' => NULL,
            'isNCBTransfer' => NULL,
            'coverages' =>
                [
                'voluntaryDeductible' => $voluntary_deductible_amount,
                'thirdPartyLiability' =>
                    [
                    'isTPPD' => $is_tppd,
                ],
                'ownDamage' =>
                    [
                    'discount' =>
                        [
                        'userSpecialDiscountPercent' => 0,
                        'discounts' =>
                        [
                        ],
                    ],
                    'surcharge' =>
                        [
                        'loadings' =>
                        [
                        ],
                    ],
                ],
                'personalAccident' =>
                    [
                    'selection' => $requestData->vehicle_owner_type == "I" ? "true" : "false",
                    'insuredAmount' => $requestData->vehicle_owner_type == "I" ? 1500000 : 0,
                    'coverTerm' => $requestData->vehicle_owner_type == "I" ? $tenure : null,
                ],
                'accessories' =>
                    [
                    'cng' =>
                        [
                        'selection' => "false",
                        'insuredAmount' => null,
                    ],
                    'electrical' =>
                        [
                        'selection' => 'false',
                        'insuredAmount' => null,
                    ],
                    'nonElectrical' =>
                        [
                        'selection' => 'false',
                        'insuredAmount' => null,
                    ],
                ],
                'addons' =>
                    [
                    'partsDepreciation' =>
                        [
                         'claimsCovered' =>$claims_covered,
                         'selection' => $zero_dep,
                    ],
                    'roadSideAssistance' => [
                        'selection' => $rsa,
                    ],
                    'engineProtection' => [
                        'selection' => $engineProtector,
                    ],
                    'returnToInvoice' => [
                        'selection' => $returnToInvoice,
                    ],
                    'consumables' => [
                        'selection' => $consum,
                    ],
                    'rimProtection' => [
                        'selection' => "false",
                    ],
                ],
                'legalLiability' =>
                    [
                    'paidDriverLL' => [
                        'selection' => $paidDriverLL,
                        'insuredCount' => $no_of_driverLL,
                    ],
                    'employeesLL' =>
                        [
                        'selection' =>"false",
                        'insuredCount' => NULL,
                    ],
                    'unnamedPaxLL' =>
                        [
                        'selection' => "false",
                        'insuredCount' => NULL,
                    ],
                    'cleanersLL' =>
                        [
                            'selection' =>"false",
                            'insuredCount' => NULL,
                    ],
                    'nonFarePaxLL' =>
                        [
                            'selection' =>"false",
                            'insuredCount' => NULL,
                    ],
                    'workersCompensationLL' =>
                        [
                            'selection' =>"false",
                            'insuredCount' => NULL,
                    ],
                ],
                'unnamedPA' =>
                [
                    'unnamedPax' =>
                    [
                        'selection' => !empty($cover_pa_unnamed_passenger) ? 'true' : 'false',
                        'insuredAmount' => !empty($cover_pa_unnamed_passenger) ? $cover_pa_unnamed_passenger : 0,
                        'insuredCount' => NULL,
                    ],
                    'unnamedPaidDriver' =>
                        [
                        'selection' => !empty($cover_pa_paid_driver) ? 'true' : 'false',
                        'insuredAmount' => !empty($cover_pa_paid_driver) ? $cover_pa_paid_driver : 0,
                        'insuredCount' => NULL,
                    ],
                    'unnamedHirer' =>
                        [
                        'selection' => "false",
                        'insuredAmount' => NULL,
                        'insuredCount' => NULL,
                    ],
                    'unnamedPillionRider' =>
                        [
                        'selection' => "false",
                        'insuredAmount' => NULL,
                        'insuredCount' => NULL,
                    ],
                    'unnamedCleaner' =>
                        [
                            'selection' => "false",
                            'insuredAmount' => NULL,
                            'insuredCount' => NULL,
                    ],
                    'unnamedConductor' =>
                        [
                            'selection' => "false",
                            'insuredAmount' => NULL,
                            'insuredCount' => NULL,
                    ],
                ],
            ],
        ],
        'vehicle' =>
        [   'isVehicleNew' => $is_vehicle_new ,
            'vehicleMaincode' => $mmv->vehicle_code,
            'licensePlateNumber' => $vehicle_registration_no != "" ? $vehicle_registration_no : str_replace('-', '', godigitRtoCode($requestData->rto_code)),
            'vehicleIdentificationNumber' => NULL,
            'engineNumber' => NULL,
            'manufactureDate' => date('Y-m-d', strtotime($motor_manf_date)),
            'registrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
            'vehicleIDV' => [
                'idv' =>  NULL,
            ],
            'usageType' => NULL,
            'permitType' => NULL,
            'motorType' => NULL,
            'vehicleType' => NULL
        ],
        'previousInsurer' =>
        [
           // 'isPreviousInsurerKnown' =>  ($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New' ? 'false' : 'true',#($requestData->previous_policy_type == 'Third-party') ? 'false' :'true',
            'isPreviousInsurerKnown' =>  $isPreviousInsurerKnown, //as per the git id #11182
             'previousInsurerCode' => "159",
            'previousPolicyNumber' => $previousPolicyNumber,
            'previousPolicyExpiryDate' => !empty($requestData->previous_policy_expiry_date) ? date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) : null,
            'isClaimInLastYear' => ($requestData->is_claim == 'Y') ? 'true' : 'false',
            'originalPreviousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',
            'previousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',#NULL,
            'previousNoClaimBonus' => $previousNoClaimBonus,
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
        'pincode' => '421201',
    ];
    if ($premium_type == "own_damage") {
        $premium_req_array['previousInsurer']['originalPreviousPolicyType'] = "1OD_5TP";
        $premium_req_array['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
        $premium_req_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = "";
        $premium_req_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = "";
        $premium_req_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d');
        $premium_req_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = Carbon::parse($requestData->vehicle_register_date)->addYear(5)->subDay(1)->format('Y-m-d');
    }
    if($isPreviousInsurerKnown == "false"){
        $premium_req_array['previousInsurer']['isPreviousInsurerKnown'] = $isPreviousInsurerKnown;
        $premium_req_array['previousInsurer']['previousInsurerCode'] = null;
        $premium_req_array['previousInsurer']['previousPolicyNumber'] = null;
        $premium_req_array['previousInsurer']['previousPolicyExpiryDate'] = null;
        $premium_req_array['previousInsurer']['isClaimInLastYear'] = null;
        $premium_req_array['previousInsurer']['originalPreviousPolicyType'] = null;
        $premium_req_array['previousInsurer']['previousPolicyType'] = null;
        $premium_req_array['previousInsurer']['previousNoClaimBonus'] = null;
        $premium_req_array['previousInsurer']['currentThirdPartyPolicy'] = null;
       }

       if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){
        $is_renewbuy = true; 
    }
    else{
        $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
    }
   
    if($is_renewbuy)
    {
            $premium_req_array['pospInfo']['isPOSP']            = false;
            $premium_req_array['pospInfo']['pospName']          = NULL;
            $premium_req_array['pospInfo']['pospUniqueNumber']  = NULL;
            $premium_req_array['pospInfo']['pospLocation']      = NULL;
            $premium_req_array['pospInfo']['pospPanNumber']     = NULL;
            $premium_req_array['pospInfo']['pospAadhaarNumber'] = NULL;
            $premium_req_array['pospInfo']['pospContactNumber'] = NULL;
    }
    //echo json_encode($premium_req_array,true)
//constants.IcConstants.godigit.GODIGIT_QUICK_QUOTE_PREMIUM
    $checksum_data = checksum_encrypt($premium_req_array);
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'godigit',$checksum_data,'BIKE');
    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
    {
        $get_response = $is_data_exits_for_checksum;
    }
    else
    {
        $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_QUICK_QUOTE_PREMIUM'),$premium_req_array, 'godigit',
        [
            'enquiryId' => $enquiryId,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'godigit',
            'section' => $productData->product_sub_type_code,
            'method' =>'Premium Calculation',
            'webUserId' => $webUserId,
            'password' =>$password,
            'transaction_type' => 'quote',
            'checksum' => $checksum_data,
            'policy_id' => $productData->policy_id
        ]);
    }
    // $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_QUICK_QUOTE_PREMIUM'),$premium_req_array, 'godigit',
    // [
    //     'enquiryId' => $enquiryId,
    //     'requestMethod' =>'post',
    //     'productName'  => $productData->product_name,
    //     'company'  => 'godigit',
    //     'section' => $productData->product_sub_type_code,
    //     'method' =>'Premium Calculation',
    //     'webUserId' => $webUserId,
    //     'password' =>$password,
    //     'transaction_type' => 'quote',
    // ]);
    $data = $get_response['response'];
    if (!empty($data)) 
    {
        $response = json_decode($data);
        $skip_second_call = false;
        if (isset($response->error->errorCode) && $response->error->errorCode == '0') 
        {
            if ($premium_type != 'third_party'){
                    $vehicle_idv = ($response->vehicle->vehicleIDV->idv);
                    $min_idv = $response->vehicle->vehicleIDV->minimumIdv;#ceil($vehicle_idv * 0.8);
                    $max_idv = $response->vehicle->vehicleIDV->maximumIdv;#floor($vehicle_idv * 1.2);
                    $default_idv = ($response->vehicle->vehicleIDV->defaultIdv);
                }else{
                    $vehicle_idv = 0;
                    $min_idv = 0;
                    $max_idv = 0;
                    $default_idv = 0;
                }

            if ($requestData->is_idv_changed == 'Y')
            {                       	
                if ($requestData->edit_idv >= $max_idv)
                {
                    $premium_req_array['vehicle']['vehicleIDV']['idv'] = $max_idv;
                    $vehicle_idv = $max_idv;
                }
                elseif ($requestData->edit_idv <= $min_idv)
                {
                    $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                    $vehicle_idv = $min_idv;
                }
                else
                {
                    $premium_req_array['vehicle']['vehicleIDV']['idv'] = $requestData->edit_idv;
                    $vehicle_idv = $requestData->edit_idv;
                }
            }else{

                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $default_idv;
                        $skip_second_call = true;
                        $vehicle_idv =  $default_idv;
                        break;
                    case 'min_idv':
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                        $vehicle_idv =  $min_idv;
                        break;
                    case 'max_idv':
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $max_idv;
                        $vehicle_idv =  $max_idv;
                        break;
                    default:
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                        $vehicle_idv =  $min_idv;
                        break;
                }
                /* $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                $vehicle_idv =  $min_idv; */
            }
            if ($vehicle_idv > 60000 && $requestData->business_type == 'breakin') {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'IDV > Rs. 60,000 is not allowed in TW break-in',
                    'request'=> [
                        'IDV' => $vehicle_idv
                    ]
                ];
            }

            if(!$skip_second_call) {

                $checksum_data = checksum_encrypt($premium_req_array);
                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'godigit',$checksum_data,'BIKE');
                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                {
                    $get_response = $is_data_exits_for_checksum;
                }
                else
                {
                    $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_QUICK_QUOTE_PREMIUM'),$premium_req_array, 'godigit',
                    [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'godigit',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Premium Re-Calculation',
                        'webUserId' =>  $webUserId,
                        'password' => $password,
                        'transaction_type' => 'quote',
                        'checksum' => $checksum_data,
                        'policy_id' => $productData->policy_id
                    ]);
                }

                // $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_QUICK_QUOTE_PREMIUM'),$premium_req_array, 'godigit',
                // [
                //     'enquiryId' => $enquiryId,
                //     'requestMethod' =>'post',
                //     'productName'  => $productData->product_name,
                //     'company'  => 'godigit',
                //     'section' => $productData->product_sub_type_code,
                //     'method' =>'Premium Re-Calculation',
                //     'webUserId' =>  $webUserId,
                //     'password' => $password,
                //     'transaction_type' => 'quote',
                // ]);
            }
                $data=$get_response['response'];
                if (!empty($data)) 
                {
                    $response = json_decode($data);
                    if ($response->error->errorCode == '0') 
                    {
                        //  $vehicle_idv = ($response->vehicle->vehicleIDV->idv);
                        //  $default_idv = ($response->vehicle->vehicleIDV->defaultIdv);
                    }
                    elseif(!empty($response->error->validationMessages[0]))
                    {
                        return 
                        [
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => str_replace(",","",$response->error->validationMessages[0])
                        ];
                    } 
                    elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
                    {
                        return 
                        [
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => str_replace(",","",$response->error->validationMessages[0])
                        ];
                    }  
                }
                else 
                {
                    return 
                    [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable'
                    ];
                }
            //}
            
            $contract = $response->contract;
            $llpaiddriver_premium = 0;
            $llcleaner_premium = 0;
            $cover_pa_owner_driver_premium = 0;
            $cover_pa_paid_driver_premium = 0;
            $cover_pa_unnamed_passenger_premium = 0;
            $cover_pa_paid_cleaner_premium = 0;
            $cover_pa_paid_conductor_premium = 0;
            $voluntary_excess = 0;
            $ic_vehicle_discount = 0;
            $cng_lpg_selected = 'N';
            $electrical_selected = 'N';
            $non_electrical_selected = 'N';
            $ncb_discount_amt = 0;
            $od = 0;
            $cng_lpg_tp = 0;
            $zero_depreciation = 0;
            $road_side_assistance = 0;
            $engine_protection = 0;
            $return_to_invoice = 0;
            $consumables = 0;
            $tppd_discount = ($is_tppd)? (($requestData->business_type == 'newbusiness') ? 250 :50):0;
            $tppd = 0;
            foreach ($contract->coverages as $key => $value) 
            {
                switch ($key) 
                {
                    case 'thirdPartyLiability':

                        if (isset($value->netPremium))
                        {
                            $tppd = (str_replace("INR ", "", $value->netPremium));
                        }
                        $is_tppd = $value->isTPPD ?? false;
                        if (!$is_tppd) {
                            $tppd_discount = 0;
                        }
                        
                    break;
        
                    case 'addons':
                        foreach ($value as $key => $addon) {
                            switch ($key) {
                                case 'partsDepreciation':
                                    if (isset($addon->coverAvailability) && $addon->selection == true && $addon->coverAvailability == 'AVAILABLE') {
                                        $zero_depreciation = (str_replace('INR ', '', $addon->netPremium));
                                    }
                                    break;

                                case 'roadSideAssistance':
                                    if (isset($addon->coverAvailability) && $addon->selection == true && $addon->coverAvailability == 'AVAILABLE') {
                                        $road_side_assistance = (str_replace('INR ', '', $addon->netPremium));
                                    }
                                    break;

                                case 'engineProtection':
                                    if (isset($addon->coverAvailability) && $addon->selection == true && $addon->coverAvailability == 'AVAILABLE') {
                                        $engine_protection = (str_replace('INR ', '', $addon->netPremium));
                                    }
                                    break;

                                case 'returnToInvoice':
                                    if (isset($addon->coverAvailability) && $addon->selection == true && $addon->coverAvailability == 'AVAILABLE') {
                                        $return_to_invoice = (str_replace('INR ', '', $addon->netPremium));
                                    }
                                    break;

                                case 'consumables':
                                    if (isset($addon->coverAvailability) && $addon->selection == true && $addon->coverAvailability == 'AVAILABLE') {
                                        $consumables = (str_replace('INR ', '', $addon->netPremium));
                                    }
                                    break;

                                }
                            }
                    break;

                    case 'ownDamage':
                       
                       if(isset($value->netPremium))
                       {
                            $od = (str_replace("INR ", "", $value->netPremium));
                            foreach ($value->discount->discounts as $key => $type) 
                            {
                                if ($type->discountType == "NCB_DISCOUNT") 
                                {
                                    $ncb_discount_amt = (str_replace("INR ", "", $type->discountAmount));
                                }
                            }
                       } 
                    break;

                    case 'legalLiability' :
                        foreach ($value as $cover => $subcover) {
                            if ($cover == "paidDriverLL") {
                                if($subcover->selection == 1) {
                                    $llpaiddriver_premium = (str_replace("INR ", "", $subcover->netPremium));
                                }
                            }
                        }
                    break;
                
                    case 'personalAccident':
                        // By default Complusory PA Cover for Owner Driver
                        if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                        {
                            $cover_pa_owner_driver_premium = (str_replace("INR ", "", $value->netPremium));
                        } 
                    break;

                    case 'accessories' :    
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
                                        $cover_pa_paid_driver_premium = (str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                            }
                            if ($cover == 'unnamedPax') {
                                if (isset($subcover->selection) && $subcover->selection == 1) {
                                    if (isset($subcover->netPremium)) {
                                        $cover_pa_unnamed_passenger_premium = (str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                            }
                        }
                    break;
                }
            }
       
            if ((isset($cng_lpg_amt) && !empty($cng_lpg_amt)) || $mmv->fuel_type == 'CNG' || $mmv->fuel_type == 'LPG') {
                $cng_lpg_tp = 60;
                $tppd = $tppd - 60;
            }
            $ncb_discount = $ncb_discount_amt;
            $final_od_premium = $od;
            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium +$tppd_discount;
            $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $tppd_discount;
            $final_net_premium   = ($final_od_premium + $final_tp_premium - $final_total_discount);

            if ($mmv->vehicle_type == 'Passenger Carrying') {
                $final_gst_amount   = ($final_net_premium * 0.18);
            } else {
                $final_gst_amount = (($tppd * 0.12) + (($final_net_premium - $tppd) * 0.18));
            }
            // $final_gst_amount = (str_replace("INR ", "", $response->serviceTax->totalTax)); // 18% IC 
            $final_payable_amount  = $final_net_premium + $final_gst_amount;
            if($requestData->business_type == 'breakin')
            {
                $applicable_addons = [];
            }

            $add_ons_data = [
                'in_built'   => [],
                'additional' => [
                                'zeroDepreciation' => $zero_depreciation,
                                'road_side_assistance' => $road_side_assistance,
                                'engine_protector' => $engine_protection,
                                'return_to_invoice' => $return_to_invoice,
                                'consumables' => $consumables
                                ]
                            ];
            if($productData->zero_dep == 0)
            {
                if($zero_depreciation > 0)
                {
                    $add_ons_data['in_built']['zeroDepreciation'] = $zero_depreciation;
                    unset($add_ons_data['additional']['zeroDepreciation']);
                }
            }
            foreach($add_ons_data['additional'] as $k=>$v){
                if($v == 0)
                {
                    unset($add_ons_data['additional'][$k]);
                }
            }

            $data_response =
            [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' =>
                [   'idv' => $vehicle_idv,
                    'min_idv' => ($min_idv),
                    'max_idv' => ($max_idv),
                    'default_idv' => $default_idv,
                    'vehicle_idv' => $vehicle_idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'rto_no' => $requestData->rto_code,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $policy_type,
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,//$requestData->vehicle_registration_no,
                    'voluntary_excess' => 0,
                    'version_id' => $mmv->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => $vehicle_idv,
                    'fuel_type' => $mmv->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => $mmv_data,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'master_policy_id' =>
                    [   'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('d-m-Y', strtotime($contract->startDate)),
                        'policy_end_date' => date('d-m-Y', strtotime($contract->endDate)),
                        'sum_insured' => $productData->sum_insured,
                        'corp_client_id' => $productData->corp_client_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'insurance_company_id' => $productData->company_id,
                        'status' => $productData->status,
                        'corp_name' => "Ola Cab",
                        'company_name' => $productData->company_name,
                        'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => "",
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $motor_manf_date,
                    'vehicleDiscountValues' =>
                    [   'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'ic_vehicle_discount' => $ic_vehicle_discount,
                    ],
                    'ic_vehicle_discount' => $ic_vehicle_discount,
                    'basic_premium' => $od,
                    'deduction_of_ncb' => $ncb_discount,
                    'tppd_premium_amount' => $tppd + $tppd_discount,
                    'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                    'seating_capacity' => $mmv->seating_capacity,
                    'default_paid_driver' => $llpaiddriver_premium,
                    'default_paid_cleaner' => $llcleaner_premium,
                    'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                    'motor_additional_paid_cleaner' => $cover_pa_paid_cleaner_premium,
                    'motor_additional_paid_conductor' => $cover_pa_paid_conductor_premium,
                    'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => $final_od_premium,
                    'total_liability_premium' => $final_tp_premium,
                    'net_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'quotation_no' => '',
                    'premium_amount'  => $final_payable_amount,
                    'antitheft_discount' => 0,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => $final_total_discount,
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($requestData->business_type =='newbusiness') ? 'New Business' : (($requestData->business_type == "breakin") ? 'Breakin' : 'Roll over'),
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),//date('d-m-Y', strtotime($contract->startDate)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),//date('d-m-Y', strtotime($contract->endDate)),
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
                    "max_addons_selection"=> NULL,
                    'applicable_addons' =>$applicable_addons,
                    'add_ons_data' =>   $add_ons_data,
                    'zd_claim_selection' => ''
                ],
            ];
            if(isset($tenure))
            {
                if($requestData->business_type == 'newbusiness' && $tenure  == 5)
                {
                    // unset($data_response['Data']['compulsory_pa_own_driver']);
                    $data_response['Data']['multi_Year_Cpa'] =  $cover_pa_owner_driver_premium;
                }
            }

            $RenewalDataApi = RenewalDataApi::where('user_product_journey_id', $requestData->user_product_journey_id)->select('api_response')->first();
            if ($requestData->is_renewal == 'Y' && $requestData->previous_insurer_code == 'godigit') {
                $api_response = json_decode($RenewalDataApi->api_response,true);
                $is_pa_owner_driver = $api_response['addons']['is_pa_owner_driver'];
                $data_response['Data']["cpa_allowed"] = $is_pa_owner_driver == true ? true : false;
            }

            if($is_geo_ext)
            {
                $data_response['Data']['GeogExtension_ODPremium'] = 0;
                $data_response['Data']['GeogExtension_TPPremium'] = 0;
            }

            if($is_tppd)
            {
                $data_response['Data']['tppd_discount'] = ($tppd_discount);
            }
            // if($is_electrical_selected)
            // {
            //     $data_response['Data']['motor_electric_accessories_value'] = 0;
            // }
            // if($is_non_electrical_selected)
            // {
            //     $data_response['Data']['motor_non_electric_accessories_value'] = 0;
            // }           
        if($is_lpg_cng)
            {
                $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                $data_response['Data']['cng_lpg_tp'] = $cng_lpg_tp;
                $data_response['Data']['motor_lpg_cng_kit_value'] = 0;
            }
        }
        elseif(!empty($response->error->validationMessages[0]))
        {
            return 
            [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => str_replace(",","",$response->error->validationMessages[0])
            ];
        } 
        elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
        {
            return 
            [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => str_replace(",","",$response->error->validationMessages[0])
            ];
        } else {
            return [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Something went wrong'
            ];
        }
    }
    else 
    {
        return
        [
            'webservice_id'=>$get_response['webservice_id'],
            'table'=>$get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Insurer not reachable'
        ];
    }
    return camelCase($data_response);
}