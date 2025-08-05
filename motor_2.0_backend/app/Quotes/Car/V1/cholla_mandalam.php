<?php

use App\Models\MasterPremiumType;
use App\Models\SelectedAddons;
use App\Models\MasterProduct;
use App\Models\chollamandalammodel;
use App\Models\ChollaMandalamRtoMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';



function getQuoteV1($enquiryId, $requestData, $productData)
{
    /* Products 
    1) BASIC -> NO ADDONS
    2) BASIC_ADDONS -> lopb, consumables, keyReplace, engineProtector, rsa, ZD EXCLUDED 
    3) ZERO_DEP -> ZERO DEP PRODUCT and lopb, consumables, keyReplace, engineProtector, rsa addons
    4) TP -> TP Product */

    DB::enableQueryLog();
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if($requestData->business_type == 'newbusiness' && $productData->premium_type_code=="third_party")
    {
        return [
            'status' => false,
            'message' => 'Quotes not allowed for Third-party Newbussiness',
        ];
    }
$cholla_model= new chollamandalammodel();
//    echo $enquiryId;die;

//        print_r($requestData);
//    print_r($productData);
//    die;

    $request_data=(array)$requestData;
    $request_data['enquiryId']=$enquiryId;

    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
    ->first();

    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $is_package         = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability       = (in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false);
    $is_od              = (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? true : false);
    $is_breakin         = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);

    $new_vehicle        = (($requestData->business_type == 'newbusiness') ? true : false);
    $is_individual      = (($requestData->vehicle_owner_type == "I" ) ? true : false);

    // if(!($requestData->business_type == 'newbusiness') && !$is_liability && ( $requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Break-In Quotes Not Allowed',
    //         'request' => [
    //             'message' => 'Break-In Quotes Not Allowed',
    //             'previous_policy_typ' => $requestData->previous_policy_type
    //         ],
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }
    // dd($requestData);
    // if(isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }

    #$car_age = car_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date);
    $expdate=(($requestData->previous_policy_expiry_date == 'New') || ($requestData->business_type == 'breakin') ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($expdate);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $car_age = ceil($age / 12);
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    // if (($interval->y >= 15) && ($tp_check == 'true')){
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 years',
    //     ];
    // }
    // if (($car_age > 10))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Quotes creation not allowed for vehicle age greater than 10 years',
    //         'request'=>array('Car age'=>$car_age),
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }
    // if (($car_age > 5) && ($productData->zero_dep == 0))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Zero dep is not allowed for vehicle age greater than 5 years',
    //         'request'=>array('Car age'=>$car_age),
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }


    $mmv = get_mmv_details($productData,$requestData->version_id,'cholla_mandalam');
    if($mmv['status'] == 1)
    {
        $mmv = $mmv['data'];
    }
    else
    {

        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request'=>$mmv,
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);


    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>array('Empty ic version code'=>$mmv_data->ic_version_code),
            'product_identifier' => $masterProduct->product_identifier,
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>array('Ic version code is DNE'=>$mmv_data->ic_version_code),
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    // if($premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Break-In Quotes Not Allowed',
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }
    //     $rto_data=DB::select("select cmpm.state_desc as state, cmrm.* from cholla_mandalam_rto_master as cmrm inner join cholla_mandalam_pincode_master as cmpm ON
    //  cmrm.num_state_code = cmpm.state_cd = 'left' where cmrm.rto ='$requestData->rto_code' limit 1");
    //  print_r($rto_data);

    if ($is_liability && $mmv_data->cubic_capacity < 1500) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Liability policy is not allowed for Cubic capacity less than 1500 CC.'
        ];
    }

    $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code,true); //DL RTO code
    
    if($rto_code[0] == 'OD')
    {
       $requestData->rto_code = 'OR-'. $rto_code[1];
    }

 $rto_data = ChollaMandalamRtoMaster::join('cholla_mandalam_pincode_master', 'cholla_mandalam_pincode_master.state_cd', '=', 'cholla_mandalam_rto_master.num_state_code')
              ->where('cholla_mandalam_rto_master.rto',$rto_code)
              ->select('cholla_mandalam_rto_master.*','cholla_mandalam_pincode_master.state_desc as state')
              ->limit(1)
              ->first();            

if($rto_data==null){
    return  [
        'premium_amount' => 0,
        'status' => false,
        'message' => 'RTO Data not found',
        'request'=>array("Rto code"=>$requestData->rto_code),
        'product_identifier' => $masterProduct->product_identifier,
    ];
}

    try {
        $motor_no_claim_bonus = '0';
        $motor_applicable_ncb = '0';
        $claimMadeinPreviousPolicy = $requestData->is_claim;
        $motor_expired_more_than_90_days = 'N'; // (Hard coded)
        if($new_vehicle){
            $claimMadeinPreviousPolicy='Y';
        }
        if ($claimMadeinPreviousPolicy == 'N') {
            $is_ncb_apllicable = true;
            $ncb_declaration = 'No';
            $motor_no_claim_bonus = $requestData->previous_ncb;
            $motor_applicable_ncb = $requestData->applicable_ncb;
        }
        else
        {
            $is_ncb_apllicable = false;
            $ncb_declaration = 'Yes';
            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
        }
        if($requestData->is_claim == 'Y'){
            $motor_no_claim_bonus = $requestData->previous_ncb;  
        }
        $product_id = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID'): ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_TP') : config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_OD')));

        $mmv_data->manf_name = $mmv_data->make;
        $mmv_data->model_name = $mmv_data->vehiclemodel;
        $mmv_data->version_name = '';
        $mmv_data->seating_capacity = $mmv_data->seating_capacity;
        $mmv_data->cubic_capacity = $mmv_data->cubic_capacity;
        $mmv_data->fuel_type = $mmv_data->fuel;
        $statecode = explode('-', $requestData->rto_code);

        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
        $motor_manf_year = $motor_manf_year_arr[1];
        $motor_manf_date = '01-' . $requestData->manufacture_year;


        $request_data['first_reg_date'] = $requestData->vehicle_register_date;
        $request_data['policy_type'] = ($is_package ? 'Comprehensive' : ($is_liability ? 'Third Party Liability' : 'Standalone OD'));

        $request_data['make'] = $mmv_data->manf_name;
        $request_data['model'] = $mmv_data->version_name;
        $request_data['fuel_type'] = $mmv_data->fuel_type;
        $request_data['cc'] = $mmv_data->cubic_capacity;
        $request_data['showroom_price'] = $mmv_data->exshowroom;
        $request_data['section'] = 'car';
        $request_data['proposal_id'] = '';
        $request_data['method'] = 'Token Generation - Quote';
        $request_data['product_id'] = $product_id;
        $request_data['model_code'] = $mmv_data->model_code;
        $request_data['rto_code'] = $rto_data['txt_rto_location_code'];
        $request_data['productName'] = $productData->product_name;
        $is_cpa = ($is_individual && !$is_od) ? true : false;


        $cpa['period'] = '1';
        //Addons
        $is_addon['zero_dep']                      = (!$is_liability && ($productData->zero_dep == 0)) ? true : false;
        $is_addon['rsa']                           = (!$is_liability) ? true : false;
        $is_addon['key_replacement']               = (!$is_liability) ? true : false;
        $is_addon['consumable']                    = (!$is_liability) ? true : false;
        $is_addon['loss_of_belongings']            = (!$is_liability) ? true : false;
        $is_addon['engine_protect']                = (!$is_liability) ? true : false;
        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
//print_r($selected_addons->accessories);
//die;

        if($masterProduct->product_identifier == 'BASIC')
        {
            $is_addon['zero_dep']                      = false;
            $is_addon['rsa']                           = false;
            $is_addon['key_replacement']               = false;
            $is_addon['consumable']                    = false;
            $is_addon['loss_of_belongings']            = false;
            $is_addon['engine_protect']                = false;
        }

        if($masterProduct->product_identifier == 'BASIC_ADDONS'){
            $is_addon['zero_dep']                      = false;
            $is_addon['rsa']                           = true;
            $is_addon['key_replacement']               = true;
            $is_addon['consumable']                    = true;
            $is_addon['loss_of_belongings']            = true;
            $is_addon['engine_protect']                = true;
        }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $bifuel = false;
        $BiFuelKitSi = '0';
        if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
        {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if($value['name'] == 'Electrical Accessories' && !$is_liability)
                {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                }
                else if($value['name'] == 'Non-Electrical Accessories' && !$is_liability)
                {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                }
                else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                {
                    $type_of_fuel = '5';
                    $bifuel = true;
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value['sumInsured'];
                    if($BiFuelKitSi < 10000 || $BiFuelKitSi > 30000)
                    {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => 'LPG/CNG cover value should be between 10000 to 30000',
                            'selected value'=> $BiFuelKitSi,
                            'request'=>array("External Bi-Fuel Kit CNG/LPG value "=>$BiFuelKitSi),
                            'product_identifier' => $masterProduct->product_identifier,
                        ];
                    }
                }
            }
        }
        
        //PA for un named passenger
        $IsPAToUnnamedPassengerCovered = false;
        $PAToUnNamedPassenger_IsChecked = false;
        $PAToUnNamedPassenger_NoOfItems = '';
        $PAToUnNamedPassengerSI = 0;
        $IsLLPaidDriver = !$is_od ? 'Yes': 'No';#bydefault select yes- confirmed by ic

        $ll_paid_driver = false;
        if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
        {
            $additional_covers = $selected_addons->additional_covers;
            foreach ($additional_covers as $value) {
                if($value['name'] == 'Unnamed Passenger PA Cover')
                {
                    $IsPAToUnnamedPassengerCovered = true;
                    $PAToUnNamedPassenger_IsChecked = true;
                    $PAToUnNamedPassenger_NoOfItems = '1';
                    $PAToUnNamedPassengerSI = $value['sumInsured'];
                    if($value['sumInsured'] != 100000){
                        return [
                            'premium_amount'    => '0',
                            'status'            => false,
                            'message'           => 'Unnamed Passenger value should be 100000 only.',
                            'request'=>array("Unnamed Passenger value"=>$value['sumInsured']),
                            'product_identifier' => $masterProduct->product_identifier,
                        ];
                    }
                }
                /* if($value['name'] == 'LL paid driver' && !$is_od)
                {
                    $IsLLPaidDriver = 'Yes';
                } */
                if($value['name'] == 'LL paid driver' && !$is_od)
                {
                    $ll_paid_driver = true;
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
        
        $is_applicable['legal_liability']                   = false;

        $is_applicable['motor_electric_accessories']        = ((!$is_liability && $IsElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_non_electric_accessories']    = ((!$is_liability && $IsNonElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_lpg_cng_kit']                 = (($is_package && ($bifuel == 'true' && $BiFuelKitSi >= 10000 && $BiFuelKitSi <= 30000)) ? true : false);


        $fuel_type_cng = false;
        if(isset($mmv_data->fyntune_version['fuel_type']) && in_array(strtoupper($mmv_data->fyntune_version['fuel_type']), ['CNG', 'LPG']))
        {
            $fuel_type_cng = true;
            $bifuel = true;
        }
        $token_response = $cholla_model->token_generation($request_data);

        if ($token_response['status'] == 'false') {
            $token_response['product_identifier'] = $masterProduct->product_identifier;
            return $token_response;
        }

        $token = $token_response['token'];
        $policy_start_date=date('d-m-Y');
        $policy_end_date = date('d-m-Y', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
        switch ($requestData->business_type) {

            case 'rollover':
                $business_type='Roll Over';
                break;

            case 'newbusiness':
                $business_type='New Business';
                  break;

            default:
                $business_type =$requestData->business_type;
                break;

        }

        if($is_breakin && $premium_type == 'third_party_breakin'){
            $policy_start_date = date('d-m-Y');
        }

        if($requestData->business_type!='newbusiness'){

            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($request_data['previous_policy_expiry_date'])));

            $prev_date=date('d-m-Y', strtotime($request_data['previous_policy_expiry_date']));

            $current_date=date('d-m-Y');
            $date1=date_create($current_date);
            $date2=date_create($prev_date);
            $diff=date_diff($date1,$date2);

           $days=(int)$diff->format("%R%a");

            // if(is_numeric($days) && $days < 0) {
            //     return [
            //         'premium_amount'    => '0',
            //         'status'            => false,
            //         'message'           => 'Breakin is not available',
            //         'request'=>array("previous_policy_expiry_date"=>$request_data['previous_policy_expiry_date']),
            //         'product_identifier' => $masterProduct->product_identifier,
            //     ];
            // }

            $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));

        }
      $tp_start_date = date('Y-m-d', strtotime('-1 year', strtotime($request_data['previous_policy_expiry_date'])));
        $tp_end_date = date('Y-m-d', strtotime('+3 year', strtotime($tp_start_date)));

        $InsuredName = 'Bharti Axa General Insurance Co. Ltd.';
        $PreviousPolExpDt = date('d-m-Y', strtotime($request_data['previous_policy_expiry_date']));
        $PreviousPolStartDt = date('d-m-Y', strtotime('-1 year + 1day', strtotime($request_data['previous_policy_expiry_date'])));
        $PolicyNo = '1234567';
        $Address1 = 'ABC';
        $Address2 = 'PQR';


        $request_data['method'] = 'IDV Calculation - Quote';
        $request_data['tp_rsd'] = (int)($cholla_model->get_excel_date($tp_start_date));
        $request_data['tp_red'] = (int)($cholla_model->get_excel_date($tp_end_date));
        $request_data['od_rsd'] = (int)($cholla_model->get_excel_date($PreviousPolStartDt));
        $request_data['od_red'] = (int)($cholla_model->get_excel_date($PreviousPolExpDt));
        $request_data['quote_db_cache'] = $productData->db_config['quote_db_cache'];

        if (!$is_liability) {
            $request_data['idv_premium_type'] = $premium_type;
            $request_data['business_type'] = $requestData->business_type;
            $idv_response = $cholla_model->idv_calculation($rto_data, $request_data, $token);
            if ($idv_response['status'] == 'false') {
                $idv_response['product_identifier'] = $masterProduct->product_identifier;
                return $idv_response;
            }
        } else {
            $idv_response['idv_range'] = [
                'idv_1' => '0',
                'idv_2' => '0',
                'idv_3' => '0',
                'idv_4' => '0',
            ];
        }
        $idv = $idv_response['idv_range']['idv_1'];

        $quote_array = [
            'PrvyrClaim' => $ncb_declaration,
            'B2B_NCB_App'                       => '',//($is_ncb_apllicable ? 'Yes' : 'No'),
            'Lastyrncb_percentage'              => '',
            // 'D2C_NCB_PERCENTAGE'                =>$motor_no_claim_bonus.'%',
            'D2C_NCB_PERCENTAGE' => $requestData->previous_policy_type != 'Third-party'? $motor_no_claim_bonus . '%' : "",

            'IMDShortcode_Dev' => config('IC.CHOLLA_MANDALAM.V1.CAR.IMDSHORTCODE_DEV'),
            'product_id' => $product_id,
            'user_code' => config('IC.CHOLLA_MANDALAM.V1.CAR.USER_CODE'),
            'intermediary_code' => '',//'2013965725280001',
            'partner_name' => '',

            'date_of_reg' => $cholla_model->get_excel_date($request_data['first_reg_date']),
            'idv_input' => (string)($is_liability ? '' : $idv),
            'ex_show_room' => ($is_liability ? '' : $request_data['showroom_price']),
            'frm_model_variant' => $request_data['model'] . ' / ' . $request_data['fuel_type'] . ' / ' . $request_data['cc'] . ' CC',
            'make' => $mmv_data->manf_name,
            'model_variant' => $mmv_data->model_name,
            'cubic_capacity' => $mmv_data->cubic_capacity,
            'fuel_type' => strtoupper($mmv_data->fyntune_version['fuel_type']),
            'vehicle_model_code' => '',

            'Customertype' => ($requestData->vehicle_owner_type ? 'Individual' : 'company'),
            'sel_policy_type'=>($premium_type=='third_party' ? 'Third Party Liability' : ($is_od ? 'Standalone OD':($requestData->business_type=='newbusiness'?'Long Term':'Comprehensive'))),

            'PAAddon'                           => ($is_cpa ? 'Yes' : 'No'),
            'pa_cover'                          => ($is_cpa ? 'Yes' : 'No'),
            'paid_driver_opted'                 => $IsLLPaidDriver,
            'unnamed_cover_opted'               => ($IsPAToUnnamedPassengerCovered ? 'Yes' : 'No'),
            'unnamed_passenger_cover_optional'  => ($PAToUnNamedPassenger_IsChecked ? 'Yes' : 'No'),
            'legal_liability_paid_driver_optional' => $IsLLPaidDriver,

            'NilDepselected' => ($is_addon['zero_dep'] ? 'Yes' : 'No'),

            'YOR' => $cholla_model->get_excel_date('01-01-' . date('Y', strtotime($request_data['first_reg_date']))),
            'prev_exp_date_comp' => $cholla_model->get_excel_date($request_data['previous_policy_expiry_date']),
            'prev_insurer_name' => 'BAJAJ',

            'title' => 'Mr',
            'fullName' => 'Full Name',
            'first_name' => 'FirstName',
            'email' => 'abc@gmail.com',
            'email_id' => 'abc@gmail.com',
            'mobile_no' => '8888888888',
            'phone_no' => '8888888888',
            'cus_mobile_no' => '8888888888',
            'state' => $rto_data['state'],

            'place_of_reg' => $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'frm_rto' => $rto_data['state'] . '-' . $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'place_of_reg_short_code' => $rto_data['txt_registration_state_code'],
            'IMDState' => $rto_data['txt_registration_state_code'],
            'city_of_reg' => $rto_data['txt_rto_location_desc'],
            'rto_location_code' => $request_data['rto_code'],
            'vehicle_model_code' => $mmv_data->model_code,

            'consumables_cover_app'  => ($is_addon['consumable'] ? 'Yes' : 'No'),
            'hydrostatic_lock_cover_app'        => ($is_addon['engine_protect'] ? 'Yes' : 'No'),
             'hydrostatic_lock_cover'            => ($is_addon['engine_protect'] ? 'Yes' : 'No'),
            'key_replacement_cover_app' => ($is_addon['key_replacement'] ? 'Yes' : 'No'),
            'no_of_unnamed' => '',
            'pc_cvas_cover' => 'No',
            'personal_belonging_cover_app'      => ($is_addon['loss_of_belongings'] ? 'Yes' : 'No'),
            'rsa_cover_app'                     => ($is_addon['rsa'] ? 'Yes' : 'No'),
            'vehicle_color' => '',

            'aadhar' => '',
            'account_no' => '',
            'address' => '||',
            'agree_checbox' => '',
            'authorizeChk' => true,
            'b2brto_master_availability' => '',
            'branch_code_sol_id' => '',
            'broker_code' => '',
            'chassis_no' => '',
            'city' => '',
            'claim_year' => '',
            'cmp_gst_no' => '',
            'commaddress' => '',
            'communi_area' => '',
            'communi_city' => '',
            'communi_houseno' => '',
            'communi_pincode' => '',
            'communi_state' => '',
            'communi_street' => '',
            'contract_no' => '',
            'covid19_addon' => 'No',
            'covid19_dcb_addon' => 'No',
            'covid19_dcb_benefit' => '',
            'covid19_lossofjob_addon' => 'No',
            'cust_mobile' => '',
            'customer_dob_input' => '',
            'd2cdtd_masterfetched' => '',
            'd2cmodel_master_availability' => '',
            'd2crto_master_availability' => '',
            'emp_code' => '',
            'employee_id' => '',
            'enach_reg' => '',
            'engine_no' => '',
            'financier_details' => '',
            'financieraddress' => '',
            'hypothecated' => 'No',
            'no_prev_ins' => ($new_vehicle ? "Yes" : 'No'),
            'no_previous_insurer_chk' => false,
            'nominee_name' => '',
            'nominee_relationship' => '',
            'od_prev_insurer_name' => '',
            'od_prev_policy_no' => '',
            'pincode' => '',
            'prev_policy_no' => '',
            'proposal_id' => '',
            'quote_id' => '',
            'reg_area' => '',
            'reg_city' => '',
            'reg_houseno' => '',
            'reg_no' => '',
            'reg_pincode' => '',
            'reg_state' => '',
            'reg_street' => '',
            'reg_toggle' => false,
            // 'save_percentage'                   => '40%',
            'sel_idv' => '',
            'seo_master_availability' => '',
            'seo_policy_type' => '',
            'seo_preferred_time' => '',
            'seo_vehicle_type' => '',
            'sol_id' => '',
            'user_type' => '',
            'usr_make' => '',
            'usr_mobile' => '',
            'usr_model' => '',
            'usr_name' => '',
            'usr_variant' => '',
            'utm_campaign' => '',
            'utm_content' => '',
            'utm_details' => '',
            'utm_medium' => '',
            'utm_source' => '',
            'utm_term' => '',
            'val_claim' => '',
            'YOM' => '',

            'externally_fitted_cng_lpg_opted'   => 'No', // $fuel_type_cng ? 'No' :($is_applicable['motor_lpg_cng_kit'] ? 'Yes' : 'No'), // https://github.com/Fyntune/motor_2.0_backend/issues/11095#issuecomment-1335125420
            'externally_fitted_cng_lpg_idv'     => '0', // $fuel_type_cng ? '0' : ($is_applicable['motor_lpg_cng_kit'] ? $BiFuelKitSi : '0'), // https://github.com/Fyntune/motor_2.0_backend/issues/11095#issuecomment-1335125420

            'cng_lpg_app'                       => ($fuel_type_cng ? 'Yes' : 'No'),
            'cng_lpg_value'                     => ($fuel_type_cng ?  '10000': '0'),

            'externally_fitted_cng_lpg_max_idv' => '',
            'externally_fitted_cng_lpg_min_idv' => '',

            'elec_acc_app' =>($is_applicable['motor_electric_accessories'] ? 'Yes' : 'No'),
            'elec_acc_desc' => '',
            'elec_acc_idv' =>$requestData->electrical_acessories_value,
            'elec_acc_max_idv' => '',
            'elec_acc_type_1' => 'electrical_accessories',
            'elec_acc_value_1' =>$requestData->electrical_acessories_value,

            'non_elec_acc_app' => ($is_applicable['motor_non_electric_accessories'] ? 'Yes' : 'No'),
            'non_elec_acc_desc' => '',
            'non_elec_acc_idv' =>$requestData->nonelectrical_acessories_value,
            'non_elec_acc_max_idv' => '',
            'non_elec_acc_type_1' => 'non_electrical_accessories',
            'non_elec_acc_value_1' =>  $requestData->nonelectrical_acessories_value,

            'save_percentage' => '',
            'od_red' => '',
            'od_rsd' => '',
            'tp_red' => '',
            'tp_rsd' => '',
            'TPPDLimit'=>6000,

        ];


//        print_r(json_encode($quote_array));

        $additional_data = [
            'requestMethod' => 'post',
            // 'Authorization' => '',
            'Authorization' => $token,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Quote Calculation - Quote',
            'section' => $request_data['section'],
            'type' => 'request',
            'productName' => $productData->product_name,
            'transaction_type' => 'quote',
        ];

        if($is_od){
            $quote_array['chola_value_added_services']  = 'No';
            $quote_array['daily_cash_allowance']        = 'No';
            $quote_array['emi_entered']                 = 0;
            $quote_array['hydrostatic_lock_cover']      = 'No';
            $quote_array['monthly_installment_cover']   = 'No';
            $quote_array['registrationcost']            = 0;
            $quote_array['reinstatement_value_basis']   = 'No';
            $quote_array['return_to_invoice']           = 'No';
            $quote_array['roadtaxpaid']                 = 0;
            // $quote_array['rto_location_code']           = '';
            $quote_array['sel_allowance']               = '';
            $quote_array['sel_time_excess']             = 0;

            $quote_array['od_red']                      = $request_data['od_red'];
            $quote_array['od_rsd']                      = $request_data['od_rsd'];
            $quote_array['tp_red']                      = $request_data['tp_red'];
            $quote_array['tp_rsd']                      = $request_data['tp_rsd'];
        }

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'cholla_mandalam', 'car');
            if ($agentDiscount['status'] ?? false) {
                $quote_array['retail_brokering_dtd_edit'] = $agentDiscount['discount'];
            } else {
                if (!empty($agentDiscount['message'] ?? '')) {
                    return [
                        'status' => false,
                        'message' => $agentDiscount['message']
                    ];
                }
            }
        }

        if(config('IC.CHOLLA_MANDALAM.V1.CAR.IS_TP_IMDSHORTCODE_DEV') == 'Y' && in_array($premium_type, ['third_party', 'third_party_breakin']))
        {
            $quote_array['IMDShortcode_Dev'] = config('IC.CHOLLA_MANDALAM.V1.CAR.TP_IMDSHORTCODE_DEV');
        }
//        print_r(json_encode($quote_array));

        $quote_url = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE') : ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_TP') : config('IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_OD')));
        $checksum_data = checksum_encrypt($quote_array);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'cholla_mandalam', $checksum_data, 'CAR');
        $additional_data['checksum'] = $checksum_data;
        if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
            $data = $is_data_exits_for_checksum;
        } else {
            $data = getWsData(
                $quote_url,
                $quote_array,
                'cholla_mandalam',
                $additional_data
            );
        }

        if (!$data['response']) {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Car Insurer Not found3',
                'product_identifier' => $masterProduct->product_identifier,

            ];
        }

        $quote_respone = json_decode($data['response'], true);

//        print_r(json_encode($quote_respone));die;
        if($quote_respone==null){
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Car Insurer Not found3',
                'product_identifier' => $masterProduct->product_identifier,
            ];

        }

        $error_message = $quote_respone['Message'] ?? $quote_respone['Status'];

        if (isset($quote_respone['Status']) && $quote_respone['Status'] != 'success') {

            $u_data = [
                'api_resp' => 'Failure',
                'api_resp_desc' => $error_message,
                'error_type' => 'Business'
            ];

            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => $error_message,
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }

        $min_idv = $idv_response['idv_range']['idv_1'];
        $max_idv = $idv_response['idv_range']['idv_3'];

        $total_idv = $idv;



        if ($requestData->is_idv_changed == 'Y')
        {

            // idv change condition
            if ($requestData->is_idv_changed == 'Y') {
                if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                    $total_idv = floor($max_idv);
                } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                    $total_idv = ceil($min_idv);
                } else {
                    $total_idv = $requestData->edit_idv;
                }
            }else{
                $total_idv = $min_idv;
            }


            $quote_array['idv_input']=(string)($is_liability ? '' : $total_idv);
            $idvchanged_additional_data = [
                'requestMethod' => 'post',
                'Authorization' => $token,
                'enquiryId' => $request_data['enquiryId'],
                'method' => 'Change IDV Calculation - Quote',
                'section' => 'car',
                'type'          => 'request',
                'productName' => $productData->product_name,
                'transaction_type' => 'quote',
            ];
            $checksum_data = checksum_encrypt($quote_array);
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'cholla_mandalam', $checksum_data, 'CAR');
            $idvchanged_additional_data['checksum'] = $checksum_data;
            if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
                $data = $is_data_exits_for_checksum;
            }else{
                $data = getWsData(
                    $quote_url,
                    $quote_array,
                    'cholla_mandalam',
                    $idvchanged_additional_data
    
                );
            }


            if (!$data['response'])
            {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status'   => false,
                    'message'  => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                    'error'		=> 'no response form service',
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }

            $quote_respone = json_decode($data['response'], true);

            if($quote_respone==null){
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status'   => false,
                    'message'  => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                    'error'		=> 'no response form service',
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }

            $error_message = $quote_respone['Message'] ?? $quote_respone['Status'];

            if(isset($quote_respone['Status']) && $quote_respone['Status'] != 'success')
            {

                $u_data =[
                    'api_resp'          => 'Failure',
                    'api_resp_desc'     => $error_message,
                    'error_type'        => 'Business'
                ];

                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'premium_amount' => 0,
                    'status'         => false,
                    'message'        => $error_message,
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }
        }



        $quote_respone = array_change_key_case_recursive($quote_respone);
        $ribbonMessage = null;

        if ($quote_array['retail_brokering_dtd_edit'] ?? false) {
            $agentDiscountPercentage = trim($quote_respone['data']['dtd_percentage'] ?? null, '%');
            if ($quote_array['retail_brokering_dtd_edit'] != $agentDiscountPercentage) {
                $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount').' '.$agentDiscountPercentage.'%';
            }
        }
        $quote_respone_data = $quote_respone['data'];
//print_r($quote_respone_data);
        $base_cover['od'] = $quote_respone_data['basic_own_damage_cng_elec_non_elec'];
        $base_cover['calc_od'] = $quote_respone_data['own_damage'];
        $base_cover['electrical'] = $quote_respone_data['electrical_accessory_prem'];
        $base_cover['non_electrical'] = $quote_respone_data['non_electrical_accessory_prem'];
        $base_cover['lpg_cng_od'] = $quote_respone_data['cng_lpg_own_damage'];
        $m_base_od=$base_cover['od']+$base_cover['lpg_cng_od']+$base_cover['electrical']+$base_cover['non_electrical'];
        $base_cover['inbuild_cng_lpg']=$base_cover['calc_od']-$m_base_od;

        $uw_loading_amount = $quote_respone_data['dtd_loading'] ?? 0;
        $uw_loading_amount = empty($uw_loading_amount) ? 0 : round($uw_loading_amount);

        $base_cover['tp'] = $quote_respone_data['basic_third_party_premium'] + $quote_respone_data['legal_liability_to_paid_driver'];
        $base_cover['pa_owner'] = $quote_respone_data['personal_accident'];
        $base_cover['unnamed'] = $quote_respone_data['unnamed_passenger_cover'];
        $base_cover['paid_driver'] = '0';
        $base_cover['legal_liability'] = $quote_respone_data['legal_liability_to_paid_driver'];
        $base_cover['lpg_cng_tp'] = $quote_respone_data['cng_lpg_tp'];

        $base_cover['ncb'] = $quote_respone_data['no_claim_bonus'];
        $base_cover['automobile_association'] = '0';
        $base_cover['anti_theft'] = '0';
        $base_cover['other_discount'] = ($quote_respone_data['dtd_discounts'] ?? $quote_respone_data['DTD_Discounts'] ?? 0) + $quote_respone_data['gst_discounts'];

        $addon['zero_dep'] = (($quote_respone_data['zero_depreciation'] == '0') ? 0 : $quote_respone_data['zero_depreciation']);
        $addon['key_replacement'] = (($quote_respone_data['key_replacement_cover'] == '0') ? 0 : $quote_respone_data['key_replacement_cover']);
        $addon['consumable'] = (($quote_respone_data['consumables_cover'] == '0') ? 0 : $quote_respone_data['consumables_cover']);
        $addon['loss_of_belongings'] = (($quote_respone_data['personal_belonging_cover'] == '0') ? 0 : $quote_respone_data['personal_belonging_cover']);
        $addon['rsa'] = (($quote_respone_data['rsa_cover'] == '0') ? 0 : $quote_respone_data['rsa_cover']);
        $addon['engine_protect']  = (($quote_respone_data['hydrostatic_lock_cover'] == '0') ? 0 : $quote_respone_data['hydrostatic_lock_cover']);
        $addon['tyre_secure'] = 0;
        $addon['return_to_invoice'] = 0;
        $addon['ncb_protect'] = 0;
        $geog_Extension_OD_Premium = 0;
        $geog_Extension_TP_Premium = 0;

        $total_premium_amount = $quote_respone_data['total_premium'];


        $base_cover['tp'] = $base_cover['tp'];// + $base_cover['legal_liability'];

        if ($addon['zero_dep'] == 'NA' && $is_addon['zero_dep']) {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep value issue',
                'car_age' => $car_age,
                'reg_date' => $requestData->vehicle_register_date,
                'request' =>array('Zero Dept value from ic'=>$addon['zero_dep']),
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }
        $add_ons_data = [];
        if($masterProduct->product_identifier == 'BASIC_ADDONS'){
            $add_ons_data = [
                'in_built' => [],
                'additional' => [
                    // 'zeroDepreciation' => $addon['zero_dep'],
                    'road_side_assistance' => $addon['rsa'],
                    'engineProtector' => $addon['engine_protect'],
                    'ncbProtection' => 0,
                    'keyReplace' => $addon['key_replacement'],
                    'consumables' => $addon['consumable'],
                    'tyreSecure' => 0,
                    'returnToInvoice' => 0,
                    'lopb' => $addon['loss_of_belongings'],
                    'cpa_cover' => $base_cover['pa_owner']
                ],
                'other' => [
                        // 'LL_paid_driver' => 0//$base_cover['legal_liability'] temprory removed abhishek need to build logic for it
                        ],
                ];
        }elseif($masterProduct->product_identifier == 'ZERO_DEP'){
            $add_ons_data = [
                'in_built' => [
                    'zeroDepreciation' => $addon['zero_dep'],
                ],
                'additional' => [
                    'road_side_assistance' => $addon['rsa'],
                    'engineProtector' => $addon['engine_protect'],
                    'ncbProtection' => 0,
                    'keyReplace' => $addon['key_replacement'],
                    'consumables' => $addon['consumable'],
                    'tyreSecure' => 0,
                    'returnToInvoice' => 0,
                    'lopb' => $addon['loss_of_belongings'],
                    'cpa_cover' => $base_cover['pa_owner']
                ],
                'other' => [
                        // 'LL_paid_driver' => 0//$base_cover['legal_liability'] temprory removed abhishek need to build logic for it
                        ],
                ];
        }elseif($masterProduct->product_identifier == 'BASIC'){
            $add_ons_data = [
                'in_built' => [],
                'additional' => [],
                'other' => [],
                ];
        }

        if(!$ll_paid_driver)
        {
            $add_ons_data['other']['LL_paid_driver'] = 0;
        }
        if($is_od)
        {
            unset($add_ons_data['other']['LL_paid_driver']);
        }
        $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));

        $add_ons = [];

        foreach ($add_ons_data as $add_on_key => $add_on_value) {
            if (count($add_on_value) > 0) {
                foreach ($add_on_value as $add_on_value_key => $add_on_value_value) {
                    if (is_numeric($add_on_value_value)) {
                        $value = (string)$add_on_value_value;
                        $base_premium_amount -= $value;
                    } else {
                        $value = $add_on_value_value;
                    }
                    $add_ons[$add_on_key][$add_on_value_key] = $value;
                }
            } else {
                $add_ons[$add_on_key] = $add_on_value;
            }
        }

        $base_premium_amount = $base_premium_amount * (1 + (18.0 / 100));


        array_walk_recursive($add_ons, function (&$item, $key) {
            if ($item == '' || $item == '0') {
                $item = 'NA';
            }
        });

        $total_od = $base_cover['od'] + $base_cover['electrical'] + $base_cover['non_electrical'] + $base_cover['lpg_cng_od']+$base_cover['inbuild_cng_lpg'];
        $total_tp = $base_cover['tp'] + $base_cover['unnamed'] + $base_cover['lpg_cng_tp']; //$base_cover['legal_liability'] +

        $total_discount = $base_cover['other_discount'] + $base_cover['automobile_association'] + $base_cover['anti_theft'] + $base_cover['ncb'];
        $basePremium = $total_od + $total_tp - $total_discount;
        $totalTax = $basePremium * 0.18;

        $final_premium = $basePremium + $totalTax;


        $applicable_addons =array();

        if (/*$car_age <=5  */!$is_liability) {
            array_push($applicable_addons,'zeroDepreciation');


        }

        if (/*$car_age <=5  */ !$is_liability) {
            array_push($applicable_addons,'roadSideAssistance');
            array_push($applicable_addons,'keyReplace');
            array_push($applicable_addons,'engineProtector');
            array_push($applicable_addons,'lopb');
            array_push($applicable_addons,'consumables');

        }
        if($productData->zero_dep == 0){
            unset($add_ons_data['additional']['zeroDepreciation']);
            $add_ons_data['in_built']['zeroDepreciation'] = $addon['zero_dep'];
        } 
        foreach($add_ons_data['additional'] as $k=>$v){
            if(empty($v)){
                unset($add_ons_data['additional'][$k]);
            }
        }
        $isInspectionWaivedOff = false;
        $waiverExpiry = null;
        if (
            $is_breakin &&
            !empty($requestData->previous_policy_expiry_date) &&
            strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
            !in_array($premium_type, ['third_party', 'third_party_breakin']) &&
            config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_INSPECTION_WAIVED_OFF') == 'Y'
        ) {
            $date1 = new DateTime($requestData->previous_policy_expiry_date);
            $date2 = new DateTime();
            $interval = $date1->diff($date2);
            
            //inspection is not required for breakin within 10 days
            if ($interval->days <= 10) {
                $isInspectionWaivedOff = true;
                $ribbonMessage = 'No Inspection Required';
                $waiverExpiry = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date .' +10 days'));
            }
        }

        $data_response = [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'status' => true,
            'msg' => 'Found',
            'product_identifier' => $masterProduct->product_identifier,
            'Data' => [
                'idv' => $premium_type == 'third_party' ? 0 : round($total_idv),
                'vehicle_idv' => $total_idv,
                'min_idv' => $min_idv,
                'max_idv' => $max_idv,
                'rto_decline' => NULL,
                'rto_decline_number' => NULL,
                'mmv_decline' => NULL,
                'mmv_decline_name' => NULL,
                'policy_type' => in_array($premium_type, ['third_party_breakin', 'third_party']) ? 'Third Party' : (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? 'Own Damage': 'Comprehensive'),
                 'cover_type' => '1YC',
                'hypothecation' => '',
                'hypothecation_name' => '',
                'vehicle_registration_no' => $rto_code,
                'rto_no' => $rto_code,
                'voluntary_excess' => $requestData->voluntary_excess_value,
                'version_id' => $mmv_data->ic_version_code,
                'showroom_price' => 0,
                'fuel_type' => $requestData->fuel_type,
                'ncb_discount' => (int)$motor_applicable_ncb,
                'company_name' => $productData->company_name,
                'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                'product_name' => $productData->product_sub_type_name,
                'mmv_detail' => $mmv_data,
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
                    'ic_vehicle_discount' =>  $base_cover['other_discount'],
                ],
                'basic_premium' => (int)$base_cover['od'],
                'deduction_of_ncb' => (int)$base_cover['ncb'],
                'tppd_premium_amount' => (int)$base_cover['tp'],
                'motor_electric_accessories_value' => (int)$base_cover['electrical'],
                'motor_non_electric_accessories_value' => (int)$base_cover['non_electrical'],
                // 'motor_lpg_cng_kit_value' => (int)$base_cover['lpg_cng_od'],
                'cover_unnamed_passenger_value' => (int)$base_cover['unnamed'],
                'seating_capacity' => $mmv_data->seating_capacity,
                'default_paid_driver' => 0,//(int)$base_cover['legal_liability'],
                'motor_additional_paid_driver' => 0,
                'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                'compulsory_pa_own_driver' => ($is_od ? 0 : (int)$base_cover['pa_owner']),
                'total_accessories_amount(net_od_premium)' => 0,
                'total_own_damage' => round($total_od),
                // 'cng_lpg_tp' => $base_cover['lpg_cng_tp'],
                'total_liability_premium' => round($total_tp),
                'net_premium' => round($basePremium),
                '18.0_amount' => 0,
                '18.0' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'voluntary_excess' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'quotation_no' => '',
                'premium_amount' => round($base_premium_amount),
                'antitheft_discount' => '',
                'final_od_premium' => round($total_od),
                'final_tp_premium' => round($total_tp),
                'final_total_discount' => round($total_discount),
                'final_net_premium' => round($base_premium_amount),
                'final_payable_amount' => round($base_premium_amount),
                'underwriting_loading_amount' => isset($uw_loading_amount) ? $uw_loading_amount : 0,
                'service_data_responseerr_msg' => 'true',
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'business_type' => $business_type,
                'service_err_code' => NULL,
                'service_err_msg' => NULL,
                'policyStartDate' => $policy_start_date,
                'policyEndDate' => $policy_end_date,
                'ic_of' => $productData->company_id,
                'ic_vehicle_discount' => $base_cover['other_discount'],
                'vehicle_in_90_days' => 0,
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
                'add_ons_data' => $add_ons_data,
                'tppd_discount' => 0,
                'applicable_addons' =>$applicable_addons,
                'ribbon' =>  $ribbonMessage
            ]
        ];

        if($bifuel == true){
            $data_response['Data']['motor_lpg_cng_kit_value'] = (int)$base_cover['lpg_cng_od'];
            $data_response['Data']['cng_lpg_tp'] = $base_cover['lpg_cng_tp'];

        }

        if($isInspectionWaivedOff) {
            $data_response['Data']['isInspectionWaivedOff'] = true;
            $data_response['Data']['waiverExpiry'] = $waiverExpiry;
        }
    }
    catch (Exception $e)
    {
        Log::info($e);
        $data_response = [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Car Insurer Not found ' .$e->getMessage(),

        ];
    }
    return camelCase($data_response);


}
