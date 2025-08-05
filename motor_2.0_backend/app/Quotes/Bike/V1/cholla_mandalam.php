<?php
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
use Carbon\Carbon;
use App\Models\SelectedAddons;
use App\Models\MasterProduct;
use App\Models\chollamandalammodel;
use Illuminate\Support\Facades\DB;
use App\Models\MasterPremiumType;
use App\Models\chollamandalamPincodeMaster;
use App\Models\ChollaMandalamBikeRtoMaster;

function getQuoteV1($enquiryId, $requestData, $productData)
{
    /* Products 
    1) BASIC -> NO ADDONS
    2) BASIC_ADDONS -> Consumables, Engine Protector, RSA
    3) ZERO_DEP -> ZERO DEP PRODUCT and Consumables, Engine Protector, RSA
    4) TP -> TP Product */

    $refer_webservice = $productData->db_config['quote_db_cache'];
    $cholla_model= new chollamandalammodel();
//    echo $enquiryId;die;

//        print_r($requestData);
//    print_r($productData);
//    die;

    $request_data=(array)$requestData;
    $request_data['enquiryId']=$enquiryId;

    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
switch ($premium_type){

    case 'third_party_breakin':
        $premium_type='third_party';
        break;
    case 'own_damage_breakin':
        $premium_type='own_damage';
        break;

}
    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);

    $is_breakin     = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);
    $new_vehicle        = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);//(($requestData->business_type == 'newbusiness') ? true : false);
    $is_individual      = (($requestData->vehicle_owner_type == "I" ) ? true : false);

    if($requestData->business_type == 'newbusiness' && $productData->premium_type_code=="third_party")
    {
        return [
            'status' => false,
            'message' => 'Quotes not allowed for Third-party Newbussiness',
        ];
    }
    
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

    $bike_age = car_age($requestData->vehicle_register_date, ($is_breakin ? date('d-m-Y') : $requestData->previous_policy_expiry_date), 'ceil');
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    // if (($bike_age >= 15) && ($tp_check == 'true')){
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 years',
    //     ];
    // }
    // if ($bike_age > 10)
    // {
    //     return ['premium_amount' => 0, 'status' => false ,'message' => 'Bike Age should not be greater than 10 years','request'=>['bike age'=>$bike_age]  ];
    // }
    // if (($bike_age > 5) && ($productData->zero_dep == 0))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Zero dep is not allowed for vehicle age greater than 4 years',
    //         'request'=>array('bike age'=>$bike_age)
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
            'request'=>$mmv
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>array('Empty ic version code'=>$mmv_data->ic_version_code)
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>array('Ic version code is DNE'=>$mmv_data->ic_version_code)
        ];
    }
    $mmv_data->seatingCapacity = $mmv_data->fyntune_version['seating_capacity'];

//     $rto_data=DB::select("select cmpm.state_desc as state, cmrm.* from cholla_mandalam_bike_rto_master as cmrm left join cholla_mandalam_pincode_master as cmpm ON
//  cmrm.num_state_code = cmpm.state_cd  where cmrm.rto ='".strtr($requestData->rto_code, ['-' => ''])."' limit 1");
//     $query = DB::getQueryLog();
    $rto_code = explode('-',$requestData->rto_code);
    if($rto_code[0] == 'OD')
    {
       $requestData->rto_code = 'OR-'. $rto_code[1];
    }
    $rto_data = ChollaMandalamBikeRtoMaster::leftjoin('cholla_mandalam_pincode_master', 'cholla_mandalam_pincode_master.state_cd', '=', 'cholla_mandalam_bike_rto_master.num_state_code')
    ->where('cholla_mandalam_bike_rto_master.rto',strtr(RtoCodeWithOrWithoutZero($requestData->rto_code, true), ['-' => '']))
    ->select('cholla_mandalam_bike_rto_master.*','cholla_mandalam_pincode_master.state_desc as state')
    ->limit(1)
    ->first(); 
//    dd($query);
//    die;
    if($rto_data==null){
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO Data not found',
            'request'=>array("Rto code"=>$requestData->rto_code)
        ];
    }
       try {
        $motor_no_claim_bonus = '0';
        $motor_applicable_ncb = '0';
        $claimMadeinPreviousPolicy = $requestData->is_claim;
        $motor_expired_more_than_90_days = 'N'; // (Hard coded)
        if ($new_vehicle) {
            $claimMadeinPreviousPolicy = 'Y';
        }
        if ($claimMadeinPreviousPolicy == 'N') {
            $is_ncb_apllicable = true;
            $ncb_declaration = 'No';
            $motor_no_claim_bonus = $requestData->previous_ncb;
            $motor_applicable_ncb = $requestData->applicable_ncb;
        } else {
            $is_ncb_apllicable = false;
            $ncb_declaration = 'Yes';
            $motor_no_claim_bonus = '0';
            $motor_applicable_ncb = '0';
        }
        $prev_date = date('d-m-Y', strtotime($request_data['previous_policy_expiry_date']));

        $current_date = date('d-m-Y');
        $date1 = date_create($current_date);
        $date2 = date_create($prev_date);
        $diff = date_diff($date1, $date2);

        $days = (int)$diff->format("%R%a");
        $date_diff=abs($days);
        $policy_start_date = date('d-m-Y');
        if($date_diff > 0){
            $policy_start_date = date('d-m-Y', strtotime('+3 day', time()));
        }
        if ($claimMadeinPreviousPolicy == 'N' && ($date_diff <= 90))
        {
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
        if ($is_liability) {
            $is_ncb_apllicable = false;
        }
    $product_id = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.BIKE.PRODUCT_ID') : ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.BIKE.PRODUCT_ID_TP') : config('IC.CHOLLA_MANDALAM.V1.BIKE.PRODUCT_ID_OD')));


//    $mmv_data->manufacturer='Bajaj';
//    $mmv_data->vehicle_mod="AVENGER - 220 STREET ABS";
//    $mmv_data->cubic_capacity='220';
//    $mmv_data->vehicle_selling_price="106624";
//    $mmv_data->vehicle_model_code='581684';
//    $rto_data['txt_rto_location_code']='12881';
        $mmv_data->manf_name = $mmv_data->manufacturer;
        $mmv_data->model_name = $mmv_data->vehicle_model;
        $mmv_data->version_name = '';//$mmv_data->vehicle_model;
        $mmv_data->cubic_capacity = $mmv_data->cubic_capacity;
        $mmv_data->fuel_type = $mmv_data->fuel_type;
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
        $request_data['showroom_price'] = $mmv_data->vehicle_selling_price;
        $request_data['section'] = 'bike';
        $request_data['proposal_id'] = '';
        $request_data['method'] = 'Token Generation - Quote';
        $request_data['product_id'] = $product_id;
        $request_data['model_code'] = $mmv_data->vehicle_model_code;
        $request_data['rto_code'] = $rto_data['txt_rto_location_code'];
        $request_data['new_vehicle'] = $new_vehicle;
        $request_data['productName'] = $productData->product_name;

        $is_cpa = ($is_individual && !$is_od) ? true : false;

        $cpa['period'] = '1';
        //Addons
        $is_addon['zero_dep'] = (/*($bike_age <= 5) && */!$is_liability && ($productData->zero_dep == 0)) ? true : false;
        $is_addon['rsa']  = (/*($bike_age <= 5) && */!$is_liability) ? true : false;
        $is_addon['engine_protect']  = (/*($bike_age <= 5) && */!$is_liability) ? true : false;
        $is_addon['consumable'] = (/*($bike_age <= 5) && */!$is_liability) ? true : false;
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
        if($masterProduct->product_identifier == 'BASIC')
        {
            $is_addon['engine_protect']                = false; 
            $is_addon['zero_dep']                      = false;
            $is_addon['rsa']                           = false;
            $is_addon['consumable']                    = false;
        }

        if($masterProduct->product_identifier == 'BASIC_ADDONS'){
            $is_addon['zero_dep']                      = false;
            $is_addon['rsa']                           = true;
            $is_addon['engine_protect']                = true;
            $is_addon['consumable']                    = true;
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        //PA for un named passenger
        $IsPAToUnnamedPassengerCovered = false;
        $PAToUnNamedPassenger_IsChecked = false;
        $PAToUnNamedPassenger_NoOfItems = '';
        $PAToUnNamedPassengerSI = 0;
        $IsLLPaidDriver='No';

        if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = $selected_addons->additional_covers;
            foreach ($additional_covers as $value) {
                if ($value['name'] == 'Unnamed Passenger PA Cover') {
                    $IsPAToUnnamedPassengerCovered = true;
                    $PAToUnNamedPassenger_IsChecked = true;
                    $PAToUnNamedPassenger_NoOfItems = '1';
                    $PAToUnNamedPassengerSI = $value['sumInsured'];
                    if ($value['sumInsured'] != 100000) {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'Unnamed Passenger value should be 100000 only.',
                            'request'=>array("Unnamed Passenger value"=>$value['sumInsured'])
                        ];
                    }
                }
                if ($value['name'] == 'LL paid driver' && !$is_od) {
                    $IsLLPaidDriver = 'Yes';
                }
            }
        }

        
        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;

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
            }
        }


        $is_applicable['motor_electric_accessories']        = ((!$is_liability && $IsElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_non_electric_accessories']    = ((!$is_liability && $IsNonElectricalItemFitted == 'true') ? true : false);

        $token_response = $cholla_model->token_generation($request_data);

        if ($token_response['status'] == 'false') {
            $token_response['product_identifier'] = $masterProduct->product_identifier;
            return $token_response;
        }

        if($token_response['status'] == 'true'){
            update_quote_web_servicerequestresponse($token_response['table'], $token_response['webservice_id'], "Token Geneartion Success", "Success" );
        }

        $token = $token_response['token'];


        $policy_start_date = date('d-m-Y');
    $policy_end_date = date('d-m-Y', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));

    $reg='New';
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
    if ($requestData->business_type != 'newbusiness') {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($request_data['previous_policy_expiry_date'])));


        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
        $reg='';
    }
    if(in_array($premium_type, ['breakin', 'own_damage_breakin','third_party_breakin']))
    {
        $policy_start_date = date('d-m-Y', strtotime('+3 day', time()));
    }
    $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));

        $tp_start_date = date('Y-m-d', strtotime('-1 year', strtotime($request_data['previous_policy_expiry_date'])));
        $tp_end_date = date('Y-m-d', strtotime('+5 year', strtotime($tp_start_date)));

        $InsuredName = 'Bharti Axa General Insurance Co. Ltd.';
        $PolicyNo = '1234567';
        $Address1 = 'ABC';
        $Address2 = 'PQR';

    $PreviousPolExpDt = date('d-m-Y', strtotime($request_data['previous_policy_expiry_date']));
    $PreviousPolStartDt = date('d-m-Y', strtotime('-1 year + 1day', strtotime($request_data['previous_policy_expiry_date'])));

        $request_data['method'] = 'IDV Calculation - Quote';
        $request_data['tp_rsd'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($tp_start_date)));
        $request_data['tp_red'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($tp_end_date)));
        $request_data['od_rsd'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($PreviousPolStartDt)));
        $request_data['od_red'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($PreviousPolExpDt)));
        $request_data['quote_db_cache'] = $productData->db_config['quote_db_cache'];

        if (!$is_liability) {
            $request_data['idv_premium_type'] = $premium_type;
            $request_data['business_type'] = $requestData->business_type;
            $idv_response = $cholla_model->idv_calculation($rto_data, $request_data, $token);
            if($idv_response['status'] == "true"){
               update_quote_web_servicerequestresponse($idv_response['table'], $idv_response['webservice_id'], "IDV Calculation Success", "Success" );
            }
//print_r(json_encode($idv_response));
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
            'PrvyrClaim'                        => $ncb_declaration,
            'B2B_NCB_App'                       => '',//($is_ncb_apllicable ? 'Yes' : 'No'),
            'Lastyrncb_percentage'              => '',
            // 'D2C_NCB_PERCENTAGE'                => $motor_no_claim_bonus.'%',
            'D2C_NCB_PERCENTAGE' => $requestData->previous_policy_type != 'Third-party'? $motor_no_claim_bonus . '%' : "",

            'IMDShortcode_Dev'                  => config('IC.CHOLLA_MANDALAM.V1.BIKE_IMDSHORTCODE_DEV'),
            'product_id'                        => $product_id,
            'user_code'                         => config('IC.CHOLLA_MANDALAM.V1.BIKE_USER_CODE'),
            'intermediary_code'                 => '',//'2013965725280001',
            'partner_name'                      => '',

            'date_of_reg'                       => $cholla_model->get_excel_date($request_data['first_reg_date']),
            'idv_input'                         => (string)($is_liability ? '' : $idv),
            'ex_show_room'                      => ($is_liability ? '' : $request_data['showroom_price']),
            'frm_model_variant'                 => $request_data['model'] . ' / ' . $request_data['fuel_type'] . ' / ' . $request_data['cc'] . ' CC',
            'make'                              => $mmv_data->manf_name,
            'model_variant'                     => $mmv_data->model_name,
            'cubic_capacity'                    => $mmv_data->cubic_capacity,
            'fuel_type'                         => 'PETROL',
            'vehicle_model_code'                => $mmv_data->vehicle_model_code,

            'Customertype' => ($requestData->vehicle_owner_type ? 'Individual' : 'company'),
            'sel_policy_type'=>($premium_type=='third_party' ? 'Liability' : ($premium_type=='own_damage' ?'Standalone OD':($requestData->business_type=='newbusiness'?'Long Term':'Comprehensive'))),

            'pa_cover'                          => ($is_cpa ? 'Yes' : 'No'),
            'PAAddon'                           => ($is_cpa ? 'Yes' : 'No'),
            'paid_driver_opted'                 => $IsLLPaidDriver,
            'unnamed_cover_opted'               => ($IsPAToUnnamedPassengerCovered ? 'Yes' : 'No'),

            'NilDepselected'                    => ($is_addon['zero_dep'] ? 'Yes' : 'No'),

            'YOR' => $cholla_model->get_excel_date($request_data['first_reg_date']),//$cholla_model->get_excel_date('01-01-' . date('Y', strtotime($request_data['first_reg_date']))),
            'prev_exp_date_comp' => (($new_vehicle) ? "" : $cholla_model->get_excel_date($PreviousPolExpDt)),
            'prev_insurer_name' => (($new_vehicle) ? "" : 'BAJAJ'),

            'title'                             => 'Mr',
            'fullName'                          => 'Full Name',
            'first_name'                        => 'FirstName',
            'email'                             => 'abc@gmail.com',
            'email_id'                          => 'abc@gmail.com',
            'mobile_no'                         => '8888888888',
            'phone_no'                          => '8888888888',
            'cus_mobile_no'                     => '8888888888',
            'state'                             => $rto_data['state'],

            'place_of_reg' => $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'frm_rto' => $rto_data['state'] . '-' . $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'place_of_reg_short_code' => $rto_data['txt_registration_state_code'],
            'IMDState' => $rto_data['txt_registration_state_code'],
            'city_of_reg' => $rto_data['txt_rto_location_desc'],
            'rto_location_code' => $request_data['rto_code'],
            'consumables_cover_app'             => ($is_addon['consumable'] ? 'Yes' : 'No'),
            // 'elec_acc_app'                      => 'No',
            // 'externally_fitted_cng_lpg_opted'   => 'No',
            // 'hydrostatic_lock_cover_app'        => 'No',
            // 'key_replacement_cover_app'         => 'No',
            // 'no_of_unnamed'                     => 'No',
            // 'non_elec_acc_app'                  => 'No',
            // 'pc_cvas_cover'                     => 'No',
            // 'personal_belonging_cover_app'      => 'No',
            'rsa_cover_app'                     => ($is_addon['rsa'] ? 'Yes' : 'No'),
            'hydrostatic_lock_cover_app'        => ($is_addon['engine_protect'] ? 'Yes' : 'No'),
            'hydrostatic_lock_cover'            => ($is_addon['engine_protect'] ? 'Yes' : 'No'),
             'vehicle_color'                     => '',
            'aadhar'                            => '',
            'account_no'                        => '',
            'address'                           => '||',
            'agree_checbox'                     => '',
            'authorizeChk'                      => true,
            'b2brto_master_availability'        => '',
            'branch_code_sol_id'                => '',
            'broker_code'                       => '',
            'chassis_no'                        => '',
            'city'                              => '',
            'claim_year'                        => '',
            'cmp_gst_no'                        => '',
            'commaddress'                       => '',
            'communi_area'                      => '',
            'communi_city'                      => '',
            'communi_houseno'                   => '',
            'communi_pincode'                   => '',
            'communi_state'                     => '',
            'communi_street'                    => '',
            'contract_no'                       => '',
            'covid19_addon'                     => 'No',
            'covid19_dcb_addon'                 => 'No',
            'covid19_dcb_benefit'               => '',
            'covid19_lossofjob_addon'           => 'No',
            'cust_mobile'                       => '',
            'customer_dob_input'                => '',
            'd2cdtd_masterfetched'              => '',
            'd2cmodel_master_availability'      => '',
            'd2crto_master_availability'        => '',
            'emp_code'                          => '',
            'employee_id'                       => '',
            'enach_reg'                         => '',
            'engine_no'                         => '',
            'financier_details'                 => '',
            'financieraddress'                  => '',
            'hypothecated'                      => 'No',
            'no_prev_ins'                       => ($new_vehicle ? "Yes" : 'No'),
            'no_previous_insurer_chk'           => false,
            'nominee_name'                      => '',
            'nominee_relationship'              => '',
            'od_prev_insurer_name'              => '',
            'od_prev_policy_no'                 => '',
            'pincode'                           => '',
            'prev_policy_no'                    => '',
            'proposal_id'                       => '',
            'quote_id'                          => '',
            'reg_area'                          => '',
            'reg_city'                          => '',
            'reg_houseno'                       => '',
            'reg_no'                            => $reg,
            'reg_pincode'                       => '',
            'reg_state'                         => '',
            'reg_street'                        => '',
            'reg_toggle'                        => false,
            'save_percentage'                   => '',
            'sel_idv'                           => '',
            'seo_master_availability'           => '',
            'seo_policy_type'                   => '',
            'seo_preferred_time'                => '',
            'seo_vehicle_type'                  => '',
            'sol_id'                            => '',
            'user_type'                         => '',
            'usr_make'                          => '',
            'usr_mobile'                        => '',
            'usr_model'                         => '',
            'usr_name'                          => '',
            'usr_variant'                       => '',
            'utm_campaign'                      => '',
            'utm_content'                       => '',
            'utm_details'                       => '',
            'utm_medium'                        => '',
            'utm_source'                        => '',
            'utm_term'                          => '',
            'val_claim'                         => '',
            'YOM'                               => '',
            // 'od_red'                            => '',
            // 'od_rsd'                            => '',
            //     'od_red'                          => $request_data['od_red'],
            // 'od_rsd'                          => $request_data['od_rsd'],
            // 'tp_red'                           => $request_data['tp_red'],
            // 'tp_rsd'                           => $request_data['tp_rsd'],

            // Accessories
            'elec_acc_app' =>($is_applicable['motor_electric_accessories'] ? 'Yes' : 'No'),
            'elec_acc_desc' => '',
            'elec_acc_idv' =>$ElectricalItemsTotalSI,
            'elec_acc_max_idv' => '',
            'elec_acc_type_1' => 'electrical_accessories',
            'elec_acc_value_1' =>$ElectricalItemsTotalSI,

            'non_elec_acc_app' => ($is_applicable['motor_non_electric_accessories'] ? 'Yes' : 'No'),
            'non_elec_acc_desc' => '',
            'non_elec_acc_idv' =>$NonElectricalItemsTotalSI,
            'non_elec_acc_max_idv' => '',
            'non_elec_acc_type_1' => 'non_electrical_accessories',
            'non_elec_acc_value_1' =>  $NonElectricalItemsTotalSI,
            // End Accessories
        ];

        if($is_od){
            $quote_array['od_red'] = $request_data['od_red'];
            $quote_array['od_rsd'] = $request_data['od_rsd'];
            $quote_array['tp_red'] = $request_data['tp_red'];
            $quote_array['tp_rsd'] = $request_data['tp_rsd'];

            $quote_array['chola_value_added_services']  = 'No';
            $quote_array['daily_cash_allowance']        = 'No';
            $quote_array['emi_entered']                 = 0;
            $quote_array['monthly_installment_cover']   = 'No';
            $quote_array['registrationcost']            = 0;
            $quote_array['reinstatement_value_basis']   = 'No';
            $quote_array['return_to_invoice']           = 'No';
            $quote_array['roadtaxpaid']                 = 0;
            $quote_array['rto_location_code']           = $request_data['rto_code'];
            $quote_array['sel_allowance']               = '';
            $quote_array['sel_time_excess']             = 0;
            $quote_array['vehicle_model_code']          = $mmv_data->vehicle_model_code;
        }  else if($requestData->business_type=='newbusiness') {

            $quote_array['plan_1']               ="Yes";

        }

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'cholla_mandalam', 'bike');
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

        if(config('IC.CHOLLA_MANDALAM.V1.BIKE.IS_TP_IMDSHORTCODE_DEV') == 'Y' && in_array($premium_type, ['third_party', 'third_party_breakin']))
        {
            $quote_array['IMDShortcode_Dev'] = config('IC.CHOLLA_MANDALAM.V1.BIKE.TP_IMDSHORTCODE_DEV');
        }
        
        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Quote Calculation - Quote',
            'section' => $request_data['section'],
            'productName' => $productData->product_name,
            'type' => 'request',
            'transaction_type' => 'quote',
        ];

//        print_r(json_encode($quote_array));
      $quote_url = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.BIKE.END_POINT_URL_QUOTE') : ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.BIKE.END_POINT_URL_QUOTE_TP') : config('IC.CHOLLA_MANDALAM.V1.BIKE.END_POINT_URL_QUOTE_OD')));
        $checksum_data = checksum_encrypt($quote_array);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'cholla_mandalam', $checksum_data, 'BIKE');
        $additional_data['checksum'] = $checksum_data;
        if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
            $get_response = $is_data_exits_for_checksum;
        } else {
            $get_response = getwsdata(
                $quote_url,
                $quote_array,
                'cholla_mandalam',
                $additional_data
            );
        }

        $data = $get_response['response'];
        if (!$data)
        {
            return [
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'premium_amount' => 0,
                'status'         => 'true',
                'message'        => 'Bike Insurer Not found3',
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }

        $quote_respone = json_decode($data, true);
//        print_r(json_encode($quote_respone));
//        die;

    if($quote_respone==null){
        return [
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Bike Insurer Not found3',
            'product_identifier' => $masterProduct->product_identifier,
        ];

    }

    if($data){
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quote Calculation Success", "Success" );
    }

    $error_message = $quote_respone['Message'] ?? $quote_respone['Status'];

    if (isset($quote_respone['Status']) && $quote_respone['Status'] != 'success') {

        $u_data = [
            'api_resp' => 'Failure',
            'api_resp_desc' => $error_message,
            'error_type' => 'Business'
        ];

        return [
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => $error_message,
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    $min_idv = $idv_response['idv_range']['idv_1'];
    $max_idv = $idv_response['idv_range']['idv_4'];

    $total_idv = $idv;

    if ($requestData->is_idv_changed == 'Y')
    {

        // idv change condition
        if ($requestData->is_idv_changed == 'Y') {
            if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                $total_idv = ceil($max_idv);
            } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                $total_idv = ceil($min_idv);
            } else {
                $total_idv = $requestData->edit_idv;
            }
        }else{
            $total_idv = $min_idv;
        }


        $quote_array['idv_input']=(string)($is_liability ? '' : $total_idv);

//        print_r(json_encode($quote_array));
        $idvchanged_additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'productName' => $productData->product_name,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Change IDV Calculation - Quote',
            'section' => 'bike',
            'type'          => 'request',
            'transaction_type' => 'quote',
        ];
            $checksum_data = checksum_encrypt($quote_array);
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'cholla_mandalam', $checksum_data, 'BIKE');
            $additional_data['checksum'] = $checksum_data;
            if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
                $data = $is_data_exits_for_checksum;
            } else {
                $get_response = getWsData(
                        $quote_url,
                        $quote_array,
                        'cholla_mandalam',
                        $idvchanged_additional_data

                    );
            }

        $data = $get_response['response'];

        if (!$data)
        {
            return [
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'status'   => false,
                'message'  => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                'error'		=> 'no response form service',
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }

        $quote_respone = json_decode($data, true);
//print_r(json_encode($quote_respone));
//die;
        if($quote_respone==null){
            return [
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
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
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
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
    $base_cover['od'] = $quote_respone_data['basic_own_damage_cng_elec_non_elec']; 
    $uw_loading_amount = $quote_respone_data['dtd_loading'] ?? 0;
    $uw_loading_amount = empty($uw_loading_amount) ? 0 : round($uw_loading_amount);

    $base_cover['electrical'] = $quote_respone_data['electrical_accessory_prem'];
    $base_cover['non_electrical'] = $quote_respone_data['non_electrical_accessory_prem'];

    $base_cover['tp'] = $quote_respone_data['basic_third_party_premium'];
    $base_cover['pa_owner'] = $quote_respone_data['personal_accident'];
    $base_cover['unnamed'] = $quote_respone_data['unnamed_passenger_cover'];
    $base_cover['paid_driver'] = '0';
    $base_cover['legal_liability'] = $quote_respone_data['legal_liability_to_paid_driver'];
    $base_cover['ncb']                      = $quote_respone_data['no_claim_bonus'];
    $base_cover['other_discount']                    = $quote_respone_data['dtd_discounts'] ?? $quote_respone_data['DTD_Discounts'] ?? 0
                                               + $quote_respone_data['gst_discounts'];
    $addon['zero_dep']                      = $quote_respone_data['zero_depreciation'];
    $addon['rsa'] = (($quote_respone_data['rsa_cover'] == '0') ? 0 : $quote_respone_data['rsa_cover']);
        $addon['engine_protect']  = (($quote_respone_data['hydrostatic_lock_cover'] == '0') ? 0 : $quote_respone_data['hydrostatic_lock_cover']);
    $addon['consumable'] = (($quote_respone_data['consumables_cover'] == '0') ? 0 : $quote_respone_data['consumables_cover']);
    $total_premium_amount                   = $quote_respone_data['total_premium'];

    $base_cover['tp'] = $base_cover['tp'];
    if($is_addon['zero_dep'] && $addon['zero_dep'] == '0'){
        return [
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'premium_amount' => 0,
            'status'         => 'true',
            'message'        => 'Zero dep is not allowed for vehicle',
            'request' =>array('Zero Dept value from ic'=>$addon['zero_dep']),
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }
    $add_ons_data = [];
    if ($masterProduct->product_identifier == 'BASIC_ADDONS') {
        $add_ons_data = [
            'in_built'   => [],
            'additional' => [
                'road_side_assistance'      => $addon['rsa'],
                'engineProtector'           => $addon['engine_protect'],
                'cpa_cover'                 => $base_cover['pa_owner'],
                'consumables'               => $addon['consumable'],
            ],
            'other'      => [],
        ];
    }elseif($masterProduct->product_identifier == 'ZERO_DEP'){
        $add_ons_data = [
            'in_built'   => [
                'zeroDepreciation'          => ($addon['zero_dep'] != '0' ? $addon['zero_dep'] : 0),
            ],
            'additional' => [
                'road_side_assistance'      => $addon['rsa'],
                'engineProtector'           => $addon['engine_protect'],
                'cpa_cover'                 => $base_cover['pa_owner'],
                'consumables'               => $addon['consumable'],
            ],
            'other'      => [],
        ];
    }elseif($masterProduct->product_identifier == 'BASIC'){
        $add_ons_data = [
            'in_built'   => [],
            'additional' => [],
            'other'      => [],
        ];
    }
    $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));

    $add_ons = [];
    foreach ($add_ons_data as $add_on_key => $add_on_value)
    {
        if (count($add_on_value) > 0)
        {
            foreach ($add_on_value as $add_on_value_key => $add_on_value_value)
            {
                if (is_numeric($add_on_value_value))
                {
                    $value = (string)$add_on_value_value;
                    $base_premium_amount -= $value ;
                }
                else
                {
                    $value = $add_on_value_value;
                }
                $add_ons[$add_on_key][$add_on_value_key] = $value;
            }
        }
        else
        {
            $add_ons[$add_on_key] = $add_on_value;
        }
    }

    $base_premium_amount = $base_premium_amount * (1 + (18.0 / 100));

    if (isset($add_ons['in_built']['road_side_assistance']) && ($add_ons['in_built']['road_side_assistance'] == '0'))
    {
        unset($add_ons['in_built']['road_side_assistance']);
        $add_ons['additional']['road_side_assistance'] = 0;
    }
    if($productData->zero_dep == 0){
        unset($add_ons_data['additional']['zeroDepreciation']);
        $add_ons_data['in_built']['zeroDepreciation'] = $addon['zero_dep'];
    } 
    foreach($add_ons['additional'] as $k=>$v){
        if(empty($v)){
            unset($add_ons['additional'][$k]);
        }
    }

    $total_od = $base_cover['od'] + $base_cover['electrical'] + $base_cover['non_electrical'];
    $total_tp = $base_cover['tp'] + $base_cover['legal_liability'] + $base_cover['unnamed'];

    $total_discount = $base_cover['other_discount'] + $base_cover['ncb'];
    $basePremium = $total_od + $total_tp - $total_discount;
    $totalTax = $basePremium * 0.18;

    $final_premium = $basePremium + $totalTax;
    $applicable_addons =array();

    if (/*$bike_age <5  && */  !$is_liability) {
        array_push($applicable_addons,'zeroDepreciation');
        array_push($applicable_addons,'consumables');
        array_push($applicable_addons,'cpaCover');
    }
    if(/*$bike_age ==0*/!$is_liability){
        array_push($applicable_addons,'engineProtector');
    }

    $data_response = [
        'webservice_id'=> $get_response['webservice_id'],
        'table'=> $get_response['table'],
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
            'policy_type' =>  ($premium_type=='third_party' ? 'Third Party' : ($premium_type=='own_damage' ?'Own Damage':'Comprehensive')),
            'cover_type' => '1YC',
            'hypothecation' => '',
            'hypothecation_name' => '',
            'vehicle_registration_no' => $requestData->rto_code,
            'rto_no' => $requestData->rto_code,
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
                'car_age' => $bike_age,
                'aai_discount' => 0,
                'ic_vehicle_discount' =>  $base_cover['other_discount'],
            ],
            'basic_premium' => (int)$base_cover['od'],
            'deduction_of_ncb' => (int)$base_cover['ncb'],
            'tppd_premium_amount' => (int)$base_cover['tp'],
            // 'motor_electric_accessories_value' => "0",
            // 'motor_non_electric_accessories_value' => "0",
            'motor_electric_accessories_value' => (int)$base_cover['electrical'],
            'motor_non_electric_accessories_value' => (int)$base_cover['non_electrical'],
            'motor_lpg_cng_kit_value' => 0,
            'cover_unnamed_passenger_value' => (int)$base_cover['unnamed'],
            'seating_capacity' => 2,
            'default_paid_driver' => (int)$base_cover['legal_liability'],
            'motor_additional_paid_driver' => 0,
            'compulsory_pa_own_driver' => ($is_od ? 0 : (int)$base_cover['pa_owner']),
            'total_accessories_amount(net_od_premium)' => 0,
            'total_own_damage' =>  ($premium_type == 'third_party') ? 0 :round($total_od),
            'cng_lpg_tp' => "0",
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
            'final_od_premium' => ($premium_type == 'third_party')? 0 :round($total_od),
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
            'ribbon' => $ribbonMessage
        ],
        'base_cover' => $base_cover
    ];

    }catch (Exception $e)
    {
        $data_response = [
            'premium_amount' => 0,
            'status'         => false,
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'message'        => 'bike Insurer Not found ' .$e->getMessage().' on Line' .$e->getLine(),

        ];
    }
    return camelCase($data_response);
}
