<?php

use App\Models\MasterPremiumType;
use App\Models\SelectedAddons;
use App\Models\MasterProduct;
use App\Models\ChollaMandalamCvModel;
use App\Models\ChollaMandalamCvRtoMaster;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    DB::enableQueryLog();
    $cholla_model = new ChollaMandalamCvModel();

    $request_data = (array)$requestData;

    $request_data['enquiryId'] = $enquiryId;
    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();

    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $parent_id = get_parent_code($productData->product_sub_type_id);


    $is_package         = (($premium_type == 'comprehensive') ? true : false);
    $is_liability       = (($premium_type == 'third_party') ? true : false);
    $is_od              = (($premium_type == 'own_damage') ? true : false);

    $new_vehicle        = (($requestData->business_type == 'newbusiness') ? true : false);
    $is_individual      = (($requestData->vehicle_owner_type == "I") ? true : false);

    if (!($requestData->business_type == 'newbusiness') && !$is_liability && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure')) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Break-In Quotes Not Allowed',
            'request' => [
                'message' => 'Break-In Quotes Not Allowed',
                'previous_policy_typ' => $requestData->previous_policy_type
            ],
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    $expdate = (($requestData->previous_policy_expiry_date == 'New') || ($requestData->business_type == 'breakin') ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($expdate);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $cv_age = ceil($age / 12);
    //Removing age validation for all iC's  #31637 
    // if (($cv_age > 10)) {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Quotes creation not allowed for vehicle age greater than 10 years',
    //         'request' => array('Cv age' => $cv_age),
    //         'product_identifier' => $masterProduct->product_identifier,
    //     ];
    // }
    // if (($cv_age > 3) && ($productData->zero_dep == 0)) {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Zero dep is not allowed for vehicle age greater than 3 years',
    //         'request' => array('Cv age' => $cv_age),
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
            'request' => $mmv,
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => array('Empty ic version code' => $mmv_data->ic_version_code),

            'product_identifier' => $masterProduct->product_identifier,
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => array('Ic version code is DNE' => $mmv_data->ic_version_code),
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    if ($premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure')) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Break-In Quotes Not Allowed',
            'product_identifier' => $masterProduct->product_identifier,
        ];
    }

    $rto_code = explode('-', $requestData->rto_code);
    if ($rto_code[0] == 'OD') {
        $requestData->rto_code = 'OR-' . $rto_code[1];
    }
    //echo $requestData->rto_code;
    $rto_data = ChollaMandalamCvRtoMaster::join('cholla_mandalam_pincode_master as pin', 'pin.state_cd', '=', 'num_state_code')
        ->where('rto', $requestData->rto_code)
        ->select('*', 'pin.state_desc as state')
        ->limit(1)
        ->first();
 
    //  $query = DB::getQueryLog();


    if ($rto_data == null) {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO Data not found',
            'request' => array("Rto code" => $requestData->rto_code),
            'product_identifier' => $masterProduct->product_identifier,
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
        if ($requestData->is_claim == 'Y') {
            $motor_no_claim_bonus = $requestData->previous_ncb;
        }
        $product_id = config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_PRODUCT_ID');
     
        $mmv_data->manf_name = $mmv_data->manufacturer;
       
        $mmv_data->model_name = $mmv_data->vehiclemodel;
        $mmv_data->version_name = $mmv_data->grossvehicleweight; //showing model name 2 times
        $mmv_data->seating_capacity = $mmv_data->seatingcapacity;
       
        $mmv_data->gvw = $mmv_data->grossvehicleweight;
        $mmv_data->cubic_capacity = $mmv_data->cubiccapacity;
        $mmv_data->fuel_type = $mmv_data->fueltype;
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
        $request_data['showroom_price'] = $mmv_data->ex_show_room;
        $request_data['section'] = $productData->product_sub_type_name ?? '';
        $request_data['proposal_id'] = '';
        $request_data['method'] = 'Token Generation - Quote';
        $request_data['product_id'] = $product_id;
        $request_data['model_code'] = $mmv_data->vehiclemodelcode;


       
        $is_cpa = ($is_individual && !$is_od) ? true : false;
       
              
        $cpa['period'] = '1';
        //Addons
        $is_addon['zero_dep']                      = (($cv_age <= 3) && !$is_liability) ? true : false;
        $is_addon['rsa']                           = (($cv_age <= 5) && !$is_liability) ? true : false;
        $is_addon['key_replacement']               = (($cv_age <= 5) && !$is_liability) ? true : false;
        $is_addon['consumable']                    = (($cv_age <= 5) && !$is_liability) ? true : false;
        $is_addon['loss_of_belongings']            = (($cv_age <= 5) && !$is_liability) ? true : false;
        $is_addon['engine_protect']                = (($cv_age <= 5) && !$is_liability) ? true : false;
        $is_addon['imt_cover']                     = (($interval->y < 10) && !$is_liability) ? true : false;
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
      

        // $is_zero_dept_selected = false;
        // foreach (($selected_addons->addons ?? []) as $value) {
        //     if ($value['name'] == 'Zero Depreciation' && $is_addon['zero_dep'] == true) {
        //         $is_zero_dept_selected = true;
        //     }
            
        // }
        
        // if ($masterProduct->product_identifier == 'WITHOUT_ADDONS') {
        //     $is_addon['zero_dep']                      = false;
        //     $is_addon['rsa']                           = false;
        //     $is_addon['key_replacement']               = false;
        //     $is_addon['consumable']                    = false;
        //     $is_addon['loss_of_belongings']            = false;
        //     $is_addon['engine_protect']                = false;
        //     $is_addon['imt_cover']                     = false;
        // }

        $IsElectricalItemFitted = false;
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = false;
        $NonElectricalItemsTotalSI = 0;
        $bifuel = false;
        $BiFuelKitSi = '0';
        if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if ($value['name'] == 'Electrical Accessories' && !$is_liability) {
                    $IsElectricalItemFitted = true;
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'Non-Electrical Accessories' && !$is_liability) {
                    $IsNonElectricalItemFitted = true;
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $type_of_fuel = '5';
                    $bifuel = true;
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value['sumInsured'];
                    if ($BiFuelKitSi < 10000 || $BiFuelKitSi > 30000) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => 'LPG/CNG cover value should be between 10000 to 30000',
                            'selected value' => $BiFuelKitSi,
                            'request' => array("External Bi-Fuel Kit CNG/LPG value " => $BiFuelKitSi),
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
        $IsLLPaidDriver = !$is_od ? 1 : 0; #bydefault select yes- confirmed by ic
        $ll_paid_driver = false;
        //ll_paid
        $is_ll_Paid_Driver = false;
        $is_ll_Paid_Cleaner = false;
        $is_ll_Paid_Coolies = false;

        $is_ll_Paid_Cleaner_no = 0;
        $is_ll_Paid_Coolies_no = 0;
        $ll_Paid_Driver_no = 0;

        if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = $selected_addons->additional_covers;
                    // $IsPAToUnnamedPassengerCovered = true;
                    // $PAToUnNamedPassenger_IsChecked = true;
                    // $PAToUnNamedPassenger_NoOfItems = '1';
                    // $PAToUnNamedPassengerSI = $value['sumInsured'];

            foreach ($additional_covers as $value) {
                // if ($value['name'] == 'Unnamed Passenger PA Cover') {
                    

                    // if ($value['sumInsured'] != 100000) {
                    //     return [
                    //         'premium_amount'    => '0',
                    //         'status'            => false,
                    //         'message'           => 'Unnamed Passenger value should be 100000 only.',
                    //         'request' => array("Unnamed Passenger value" => $value['sumInsured']),
                    //         'product_identifier' => $masterProduct->product_identifier,
                    //     ];
                    // }

                    // if ($value['name'] == 'LL paid driver') {
                    //     $is_ll_Paid_Driver = true;
                    //     $is_ll_Paid_Cleaner = true;
                    //     $is_ll_Paid_Coolies = true;

                    //     $ll_Paid_Driver_no = 1;
                    // }
                     if ($value['name'] == 'LL paid driver/conductor/cleaner') {
                        $is_ll_Paid_Driver = in_array('DriverLL', $value['selectedLLpaidItmes']) ? true : false;
                        $ll_Paid_Driver_no = $value['LLNumberDriver'] ?? 0;    
                        
                        
                        $is_ll_Paid_Cleaner = in_array('CleanerLL', $value['selectedLLpaidItmes']) ? true : false;
                        $is_ll_Paid_Cleaner_no = $value['LLNumberCleaner'] ?? 0;


                        $is_ll_Paid_Coolies = in_array('CooliesLL',$value['selectedLLpaidItmes']) ? true : false;
                        $is_ll_Paid_Coolies_no =$value['LLNumberConductor']?? 0;
                       
                    }
                // }
                
                /* if($value['name'] == 'LL paid driver' && !$is_od)
            {
                $IsLLPaidDriver = 'Yes';
            } */
                if ($value['name'] == 'LL paid driver' && !$is_od) {
                    $ll_paid_driver = true;
                }
            }
            
        }

        $IsAntiTheftDiscount = 'false';

        if ($selected_addons && $selected_addons->discount != NULL && $selected_addons->discount != '') {
            $discount = $selected_addons->discount;
            foreach ($discount as $value) {
                if ($value->name == 'anti-theft device') {
                    $IsAntiTheftDiscount = 'true';
                }
            }
        }

        $is_applicable['legal_liability']                   = false;

        $is_applicable['motor_electric_accessories']        = ((!$is_liability && $IsElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_non_electric_accessories']    = ((!$is_liability && $IsNonElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_lpg_cng_kit']                 = (($is_package && ($bifuel == 'true' && $BiFuelKitSi >= 10000 && $BiFuelKitSi <= 30000)) ? true : false);


        $fuel_type_cng = false;
        if (isset($mmv_data->fyntune_version['fuel_type']) && in_array(strtoupper($mmv_data->fyntune_version['fuel_type']), ['CNG', 'LPG'])) {
            $fuel_type_cng = true;
            $bifuel = true;
        }

        // token code 

        $token_response = $cholla_model->token_generation($request_data);
        $token = $token_response['token'];

      
        $policy_start_date = date('d-m-Y');

        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));

        switch ($requestData->business_type) {

            case 'rollover':
                $business_type = 'Roll Over';
                break;

            case 'newbusiness':
                $business_type = 'New';
                break;

            default:
                $business_type = $requestData->business_type;
                break;
        }

        if ($requestData->business_type != 'newbusiness') {

            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($request_data['previous_policy_expiry_date'])));

            $prev_date = date('d-m-Y', strtotime($request_data['previous_policy_expiry_date']));

            $current_date = date('d-m-Y');
            $date1 = date_create($current_date);
            $date2 = date_create($prev_date);
            $diff = date_diff($date1, $date2);




            $days = (int)$diff->format("%R%a");

            if (is_numeric($days) && $days < 0) {
                return [
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => 'Breakin is not available',
                    'request' => array("previous_policy_expiry_date" => $request_data['previous_policy_expiry_date']),
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }

            $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
        }


        $tp_start_date = date('Y-m-d', strtotime('-1 year', strtotime($request_data['previous_policy_expiry_date'])));
        $tp_end_date = date('Y-m-d', strtotime('+1 year', strtotime($tp_start_date)));

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
        $request_data['Dor']    = (int)($cholla_model->get_excel_date($requestData->vehicle_register_date));
        $request_data['sub_Class'] = (int)(($mmv_data->sub_class == 'A1- 4 WHEELER VEHICLES (PUBLIC)')?'A1':'A3');
        $request_data['prevpolicyexp'] = (int)($cholla_model->get_excel_date($request_data['previous_policy_expiry_date']));
     
        $is_imt = (($masterProduct->product_identifier === "GCV Insurance - IMT23") && $interval->y < 10) ? true : false;

        if (!$is_liability) {
            $request_data['idv_premium_type'] = $premium_type; 
            $request_data['business_type'] = $requestData->business_type;
            $idv_response = $cholla_model->idv_calculation($rto_data, $request_data, $token);
           
            

            if ($idv_response['status'] == false) {
                $idv_response['product_identifier'] = $masterProduct->product_identifier;
                return [ 
                    'status'    => false,
                    'message'   => 'GVW not in the master data'
                ];
            }
        } else {
            $idv_response['idv_range'] = [
                'chassis_price_input' => '0',
                'chassis_min' => '0',
                'chassis_max' => '0',

            ];
        }
        $body_input = 0;
        $chsis_input = 0;

        if (!$is_liability) {
            if ($mmv_data->txt_fbv_flag == "FBV") {
                $body_input = $idv_response['idv_range']['body_price_input'];
                $chsis_input = $idv_response['idv_range']['chassis_price_input'];
            } elseif ($mmv_data->txt_fbv_flag == "Non-FBV") {
                $chsis_input = $idv_response['idv_range']['chassis_price_input'];
                $body_input = $idv_response['idv_range']['body_price_input'];
            }
        }
    
        $idv = (!$is_liability)?$idv_response['idv_range']['chassis_min']:0;
        $default_idv = (!$is_liability)?$idv_response['idv_range']['chassis_min']:0;

        $min_idv = ($mmv_data->txt_fbv_flag == "FBV") ? $idv_response['idv_range']['chassis_min']  : $default_idv ;
        $max_idv = ($mmv_data->txt_fbv_flag == "FBV") ? $idv_response['idv_range']['chassis_max']  : $default_idv ;
        
        $total_idv = $idv;
        if ($mmv_data->txt_fbv_flag == "FBV") {

            // idv change condition
            if ($requestData->is_idv_changed == 'Y') {
                if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                    $total_idv = floor($max_idv);
                } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                    $total_idv = ceil($min_idv);
                } else {
                    $total_idv = $requestData->edit_idv;
                }
            } else {
                $total_idv = $idv;
            }
        } else {
            $total_idv = $default_idv;
        }
        
        $mmv_data->app_product_name = 'gccv';
        $quote_array = [
            "user_code" => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_USER_CODE'),
            "IMDShortcode_Dev" => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_IMDSHORTCODE_DEV'),
            "parent_policy_no" => "",
            "renewal_id" => "",
            "business_transaction_type" => $business_type,
            "product_name1" => $is_liability ? 'Liability' : 'Comprehensive',

            //MMV
            "vehicle_class_dev" => (($mmv_data->sub_class == 'A1- 4 WHEELER VEHICLES (PUBLIC)')?'A1':'A3'),
            "vehicle_model_code" => $mmv_data->vehiclemodelcode,
            "app_product_name" =>$mmv_data->app_product_name,
            "gvw_per_rc" =>  $mmv_data->gvw,    
            "DOR" => $cholla_model->get_excel_date($request_data['first_reg_date']),
            "area_code" => "12192",
            "rto_location_code" => $rto_data['txt_rto_location_code'],
            "chassis_price_edit" => $chsis_input,
            "body_price_edit" => $body_input,
            "edit_total_seating_capacity" => $mmv_data->seating_capacity,
            "no_of_drivers" => $IsLLPaidDriver,
            "no_of_cleaners" => $is_ll_Paid_Cleaner_no,
            "no_of_coolies" => $is_ll_Paid_Coolies_no,
            // "dtd_input_rate" => 68, 
            "prev_policy_exp" => $new_vehicle ? "NEW" : $cholla_model->get_excel_date($request_data['previous_policy_expiry_date']),
            "ncb_app" =>  ($is_ncb_apllicable ? 'Yes' : 'No'),
            "claim_history" => $requestData->previous_ncb.'%',
            "ncb_confirmation" => true,
            "imt_cover" => ($is_imt == true ? 'Yes': 'No'),
            "pa_cover" => ($is_cpa ? 'Yes' : 'No'),
            "tipper_jack_opted" => "No",
            //Addon
            "nil_dep_cover" => ($is_addon['zero_dep']  == true ? 'Yes' : 'No'),
            "NilDepselected" =>  ($is_addon['zero_dep']  == true ? 'Yes' : 'No'),
            //customer
            "salutation" => "Mr",
            "first_name" => "Pankaj",
            "customer_full_name" => "Pankaj",
            "company_name" => "Pankaj",
            "customer_aadhar" => "8976 5435 6787",
            "phone_no" => "8882515175",
            "mobile_no" => "8882515175",
            "email" => "me.pankajpathak@gmail.com",
            "customer_dob" => 37784,
            "customer_owner_type" => ($requestData->vehicle_owner_type ? 'Individual' : 'company'),
            "gstin_no" => "",
            "customer_gender" => "",
            "customer_age_dev" => null,
            "reg_no" => "",
            "YOM" => $motor_manf_year,
            "engine_no" => "756534323",
            "chassis_no" => "32454543254",
            "frm_prev_insurer" => "",
            "od_prev_insurer" => "",
            "od_prev_policy_no" => "",
            "hypothecated_yes_no" => "",
            "bank_name" => "",
            "branch_name" => "",
            "branch_address" => "",
            "pincode" => "",
            "state" => $rto_data['state'],
            "city" => "",
            "area" => "",
            "place_of_reg" => $rto_data['txt_registration_state_code'],
            "address" => "",
            "street" => "",
            "comm_diff" => "",
            "pincode1" => "",
            "state1" => "",
            "city1" => "",
            "area1" => "",
            "address1" => "",
            "street1" => "",
            "nominee_name" => "",
            "nominee_relationship" => "",
            "product_id" => $product_id,
            "quote_id" => "",
            "proposal_id" => ""
        ];

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'cholla_mandalam', strtolower($parent_id));
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

        $header = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Bearer ' . $token
        ];

        //        print_r(json_encode($quote_array));

        $additional_data = [
            'requestMethod' => 'post',
            // 'Authorization' => '',
            'headers' => $header,
            'Authorization' => $token,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Quote Calculation - Quote',
            'section' => $request_data['section'],
            'type' => 'request',
            'productName' => $productData->product_name,
            'transaction_type' => 'quote',
        ];

        $quote_url = config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_CV_QUOTE');

        $data = getWsData($quote_url, $quote_array, 'cholla_mandalam', $additional_data);
        if (!$data['response']) {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'CV Insurer Not found3',
                'product_identifier' => $masterProduct->product_identifier,
            ];
        }

        $quote_respone = json_decode($data['response'], true);
        if ($quote_respone == null) {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'CV Insurer Not found3',
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
        
        // $min_idv_fBV = ((!$is_liability)?$idv_response['idv_range']['body_price_min']:0)+ $idv_response['idv_range']['chassis_min'];
        // $max_idv_fBV = ((!$is_liability)?$idv_response['idv_range']['body_price_max']:0)+ $idv_response['idv_range']['chassis_max'];
            $quote_array['idv_input'] = (string)($is_liability ? '' : $total_idv);
            $idvchanged_additional_data = [
                'requestMethod' => 'post',
                'Authorization' => $token,
                'enquiryId' => $request_data['enquiryId'],
                'method' => 'Change IDV Calculation - Quote',
                'section' => 'GCV',
                'type'          => 'request',
                'productName' => $productData->product_name,
                'transaction_type' => 'quote',
            ];
            $data = getWsData(
                $quote_url,
                $quote_array,
                'cholla_mandalam',
                $idvchanged_additional_data
            );

            if (!$data['response']) {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status'   => false,
                    'message'  => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                    'error'        => 'no response form service',
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }

            $quote_respone = json_decode($data['response'], true);
            if ($quote_respone == null) {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status'   => false,
                    'message'  => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                    'error'        => 'no response form service',
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }

            $error_message = $quote_respone['Message'] ?? $quote_respone['Status'];

            if (isset($quote_respone['Status']) && $quote_respone['Status'] != 'success') {

                $u_data = [
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
       

        $quote_respone = array_change_key_case_recursive($quote_respone);

        $ribbonMessage = null;
        if ($quote_array['retail_brokering_dtd_edit'] ?? false) {
            $agentDiscountPercentage = trim($quote_respone['data']['dtd_percentage'] ?? null, '%');
            if ($quote_array['retail_brokering_dtd_edit'] != $agentDiscountPercentage) {
                $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount') . ' ' . $agentDiscountPercentage . '%';
            }
        }

        $quote_respone_data = $quote_respone['data'];
        $base_cover['od'] = $quote_respone_data['basic_own_damage_cng_elec_non_elec'];
        $base_cover['electrical'] = $quote_respone_data['electrical_accessory_prem'];
        $base_cover['non_electrical'] = $quote_respone_data['non_electrical_accessory_prem'];
        $base_cover['lpg_cng_od'] = $quote_respone_data['cng_lpg_own_damage'];
        
        $uw_loading_amount = $quote_respone['dtd_loading'] ?? 0;
        $uw_dtd_percentage = $quote_respone['dtd_percentage'] ?? 0;

        $base_cover['tp'] = $quote_respone_data['basic_third_party_premium'] - $quote_respone_data['cng_lpg_tp'] + $quote_respone_data['legal_liability_to_paid_driver'];
        $base_cover['pa_owner'] = $quote_respone_data['personal_accident'];
        $base_cover['unnamed'] = $quote_respone_data['unnamed_passenger_cover'];
        $base_cover['paid_driver'] = $quote_respone_data['legal_liability_to_paid_driver'];
        $base_cover['legal_liability'] = $quote_respone_data['paid_coolie_cleaner_premium'] ;
        $base_cover['lpg_cng_tp'] = $quote_respone_data['cng_lpg_tp'];

        $base_cover['ncb'] = $quote_respone_data['no_claim_bonus'];
        $base_cover['ncb_per'] = $quote_respone_data['ncb_percentage'];
        $base_cover['automobile_association'] = '0';
        $base_cover['anti_theft'] = '0';
        // $base_cover['tppd_discount'] = $base_cover['tppd_discount_premium'];

        $base_cover['other_discount'] = $quote_respone_data['dtd_discounts'] + $quote_respone_data['gst_discounts'];
      
        $addon['zero_dep'] = (($quote_respone_data['zero_depreciation'] == '0') ? 0 : $quote_respone_data['zero_depreciation']);
        $addon['key_replacement'] = (($quote_respone_data['key_replacement_cover'] == '0') ? 0 : $quote_respone_data['key_replacement_cover']);
        $addon['consumable'] = (($quote_respone_data['consumables_cover'] == '0') ? 0 : $quote_respone_data['consumables_cover']);
        $addon['loss_of_belongings'] = (($quote_respone_data['personal_belonging_cover'] == '0') ? 0 : $quote_respone_data['personal_belonging_cover']);
        $addon['rsa'] = (($quote_respone_data['rsa_cover'] == '0') ? 0 : $quote_respone_data['rsa_cover']);
        $addon['imt_cover'] = (($quote_respone_data['imt_cover_premium'] == '0') ? 0 : $quote_respone_data['imt_cover_premium']);

        $addon['engine_protect']  = (($quote_respone_data['hydrostatic_lock_cover'] == '0') ? 0 : $quote_respone_data['hydrostatic_lock_cover']);
        $addon['tyre_secure'] = 0;
        $addon['return_to_invoice'] = 0;
        $addon['ncb_protect'] = 0;
        $geog_Extension_OD_Premium = 0;
        $geog_Extension_TP_Premium = 0;

        $total_premium_amount = $quote_respone_data['total_premium'];


        $add_ons_data = [
            'in_built' => [],
            'additional' => [
                'zeroDepreciation' => $addon['zero_dep'],
                'road_side_assistance' => $addon['rsa'],
                'imt23' => $addon['imt_cover']
            ],
            'other' => [
            ],
        ];
        if ($addon['zero_dep'] == 'NA' && $is_addon['zero_dep']) {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status' => false,
                'message' => 'Zero dep value issue',
                'cv_age' => $cv_age,
                'reg_date' => $requestData->vehicle_register_date,
                'request' => array('Zero Dept value from ic' => $addon['zero_dep']),
                'product_identifier' => $masterProduct->product_identifier,
            ];
            $add_ons_data = [
                'in_built' => [
                    'zeroDepreciation' => $addon['zero_dep'],
                ],
                'additional' => [
                    'road_side_assistance' => $addon['rsa'],
                    'imt23' => $addon['imt_cover']
                ],
                'other' => [
                ],
            ];
        }

        if ($masterProduct->product_identifier === "GCV Insurance - IMT23") {
            if (empty($addon['imt_cover'])) {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status' => false,
                    'message' => 'IMT - 23 value issue',
                    'cv_age' => $cv_age,
                    'reg_date' => $requestData->vehicle_register_date,
                    'request' => array('addon' => $addon),
                    'product_identifier' => $masterProduct->product_identifier,
                ];
            }
            $add_ons_data = [
                'in_built' => [
                    'imt23' => $addon['imt_cover']
                ],
                'additional' => [
                    'zeroDepreciation' => $addon['zero_dep'],
                    'road_side_assistance' => $addon['rsa'],
                ],
                'other' => [
                    // 'LL_paid_driver' => 0//$base_cover['legal_liability'] temprory removed abhishek need to build logic for it
                ],
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

        $total_od = $base_cover['od'] + $base_cover['electrical'] + $base_cover['non_electrical'] + $base_cover['lpg_cng_od'];
        $total_tp = $base_cover['tp'] +  $base_cover['legal_liability'] +   $base_cover['lpg_cng_tp'];
        $total_discount = $base_cover['other_discount'] + $base_cover['ncb'];
        $basePremium = $total_od + $total_tp - $total_discount;
        $totalTax = $basePremium * 0.18;
        $final_premium = $basePremium + $totalTax;

        $applicable_addons = array();

        if ($cv_age <= 3  && !$is_liability && $quote_respone_data['zero_depreciation'] != '0' &&$quote_respone_data['zero_depreciation'] != '')  {
            array_push($applicable_addons, 'zeroDepreciation');
        }
       
        if ($cv_age <= 5  && !$is_liability && $quote_respone_data['rsa_cover'] != '0' && $quote_respone_data['rsa_cover'] != '') {
            array_push($applicable_addons, 'roadSideAssistance');
        }
       
        if ($interval->y < 10  && !$is_liability && $quote_respone_data['imt_cover_premium'] != '0' && $quote_respone_data['imt_cover_premium'] != '') {
            array_push($applicable_addons, 'imt23');
        }
        if ($cv_age <= 5  && !$is_liability && $quote_respone_data['paid_coolie_cleaner_premium'] != '0' && $quote_respone_data['paid_coolie_cleaner_premium'] != '') {
            array_push($applicable_addons, 'paid_coolie_cleaner_premium');
        }

        $data_response = [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'status' => true,
            'msg' => 'Found',
            'product_identifier' => $masterProduct->product_identifier,
            'Data' => [
                'idv' => ($premium_type == 'third_party') ? 0 : round($total_idv),
                'vehicle_idv' => $total_idv,
                'min_idv' => $min_idv,
                'max_idv' => $max_idv,
                'bodyIDV'                   => $body_input,
                'minBodyIDV'                => ($premium_type == 'third_party') ? 0 : $idv_response['idv_range']['body_price_min'],
                'maxBodyIDV'                => ($premium_type == 'third_party') ? 0 : $idv_response['idv_range']['body_price_max'],

                'chassisIDV'                => $chsis_input,
                'minChassisIDV'             => ($premium_type == 'third_party') ? 0 : $idv_response['idv_range']['chassis_min'],
                'maxChassisIDV'             => ($premium_type == 'third_party') ? 0 : $idv_response['idv_range']['chassis_max'],
                'rto_decline' => NULL,
                'rto_decline_number' => NULL,
                'mmv_decline' => NULL,
                'mmv_decline_name' => NULL,
                'policy_type' => ($premium_type == 'third_party' ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive')),
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
                    'cv_age' => $cv_age,
                    'aai_discount' => 0,
                    'ic_vehicle_discount' =>  $base_cover['other_discount'],
                ],
                'ribbon' => $ribbonMessage,
                'basic_premium' => (int)$base_cover['od'],
                'deduction_of_ncb' => (int)$base_cover['ncb'],
                'tppd_premium_amount' => (int)$base_cover['tp'],
                'motor_electric_accessories_value' => (int)$base_cover['electrical'],
                'motor_non_electric_accessories_value' => (int)$base_cover['non_electrical'],
                'cover_unnamed_passenger_value' => (int)$base_cover['unnamed'],
                'seating_capacity' => $mmv_data->seatingcapacity,
                // 'default_paid_driver' => 0,//(int)$base_cover['legal_liability'] ,
                'motor_additional_paid_driver' => 0,
                'll_paid_conductor_premium'                 => $base_cover['legal_liability'] ,
                'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                'compulsory_pa_own_driver' => ($is_od ? 0 : (int)$base_cover['pa_owner']),
                'total_accessories_amount(net_od_premium)' => 0,
                'total_own_damage' => round($total_od),
                'total_liability_premium' => round($total_tp),
                'net_premium' => round($basePremium),
                'service_tax_amount' =>  $totalTax,
                'service_tax' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'voluntary_excess' => 0,
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
                'business_type' => $business_type == "New" ? "New Business" : $business_type,
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
                'applicable_addons' => $applicable_addons
            ]
        ];

        if ($bifuel == true) {
            $data_response['Data']['motor_lpg_cng_kit_value'] = (int)$base_cover['lpg_cng_od'];
            $data_response['Data']['cng_lpg_tp'] = $base_cover['lpg_cng_tp'];
            $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
        }
    } catch (Exception $e) {
        $data_response = [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'CV Insurer Not found ' . $e->getMessage().' line - '.$e->getLine(),

        ];
    }
    return camelCase($data_response);
}

