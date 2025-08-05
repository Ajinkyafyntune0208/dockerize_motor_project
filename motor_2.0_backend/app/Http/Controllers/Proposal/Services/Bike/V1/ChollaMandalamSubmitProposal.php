<?php

namespace App\Http\Controllers\Proposal\Services\Bike\V1;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

use App\Http\Controllers\SyncPremiumDetail\Bike\ChollaMandalamPremiumDetailController;
use App\Models\PolicyDetails;
use Carbon\Carbon;
use DateTime;
use App\Models\MasterProduct;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use App\Models\chollamandalammodel;
use Illuminate\Support\Facades\DB;
use App\Models\MasterPremiumType;
use App\Models\QuoteLog;

class ChollaMandalamSubmitProposal
{

    public static function submit($proposal, $request)
    {
        $cholla_model = new chollamandalammodel();
        DB::enableQueryLog();
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed because zero dep is not part of your previous policy',
            ];
        } */
        $getdata = json_encode($proposal);
        $proposal = json_Decode($getdata);
        $additional_details = json_decode($proposal->additional_details);
        $proposal_date = date('d/m/Y');
        $is_new = (($proposal->business_type == 'N') ? true : false);
        $is_individual = (($requestData->vehicle_owner_type == 'I') ? true : false);
        $is_financed = (($proposal->financer_agreement_type == '' || $proposal->financer_agreement_type == 'None') ? false : true);
        $new_vehicle = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);//(($requestData->business_type == 'newbusiness') ? true : false);
        $quote = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $mmv = get_mmv_details($productData, $requestData->version_id, 'cholla_mandalam');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv = (object)array_change_key_case((array)$mmv, CASE_LOWER);
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        switch ($premium_type) {

            case 'third_party_breakin':
                $premium_type = 'third_party';
                break;
            case 'own_damage_breakin':
                $premium_type = 'own_damage';
                break;

        }
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $is_package = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_od = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);

        $is_breakin = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);

        $gender = $proposal->gender;
        $marital_status = $proposal->marital_status;
        $occupation = $proposal->occupation;
        $AgreementType = $proposal->financer_agreement_type;

        $previous_insurer_address_details = DB::table('insurer_address')->where('Insurer', 'like', $proposal->insurance_company_name . '%')->first();
        $previous_insurer_address_details = keysToLower($previous_insurer_address_details);


        if ($is_od) {

//            $tp_previous_insurer_address_details    = get_details(
//                'previous_insurer_mappping',
//                ['addressLine1', 'addressLine2', 'pincode'],
//                ['previous_insurer' => $tp_insurercode]
//            );
//            $tp_policy_address1           = $tp_previous_insurer_address_details[0]['addressLine1'];
//            $tp_policy_address2           = $tp_previous_insurer_address_details[0]['addressLine2'];
//            $tp_policy_pincode            = $tp_previous_insurer_address_details[0]['pincode'];
        } else {
            $tp_policy_address1 = $tp_policy_address2 = $tp_policy_pincode = '';
        }

        if ($is_od) {
//            $tp_insurercode = get_previous_insurer($tp_insurercode,'cholla_mandalam');
            $tp_insurercode = '';
        }

//        $is_zero_dep = (($productData->zero_dep == '0') ? true : false);

        $product_name = $productData->product_name;
        $company_name = $productData->company_name;
        
        // $rto_code = explode('-',$requestData->rto_code);
        $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code,true); //DL RTO code
        if(substr($rto_code, 0, 2) == 'OD')
        {
           $requestData->rto_code = 'OR'. substr($rto_code, 2);
           $rto_code = $requestData->rto_code;
        }
        $rto_data = DB::select("select cmpm.state_desc as state, cmrm.* from cholla_mandalam_bike_rto_master as cmrm left join cholla_mandalam_pincode_master as cmpm ON
        cmrm.num_state_code = cmpm.state_cd  where cmrm.rto ='" . strtr($rto_code, ['-' => '']) . "' limit 1");
        
        $rto_data = (array)$rto_data[0];
        
        $reg_no='';
        if($proposal->vehicale_registration_number!='NEW'){
            $reg_no = $proposal->vehicale_registration_number;
            $reg_no = explode('-', $reg_no);

            if ($reg_no[0] == 'DL') {
                $registration_no = RtoCodeWithOrWithoutZero($reg_no[0].$reg_no[1]);
                $reg_no = $registration_no.'-'.$reg_no[2].'-'.$reg_no[3];
            } else {
                $reg_no = $proposal->vehicale_registration_number;
            }
            // $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';
        }

        $reg_no='';
        if($proposal->vehicale_registration_number!='NEW'){
            $reg_no = $proposal->vehicale_registration_number;
            $reg_no = explode('-', $reg_no);

            if ($reg_no[0] == 'DL') {
                $registration_no = RtoCodeWithOrWithoutZero($reg_no[0].$reg_no[1]);
                $reg_no = $registration_no.'-'.$reg_no[2].'-'.$reg_no[3];
            } else {
                $reg_no = $proposal->vehicale_registration_number;
            }
            // $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();


        $bike_age = car_age($requestData->vehicle_register_date, ($is_breakin ? date('d-m-Y') : $requestData->previous_policy_expiry_date), 'ceil');

        $addon = [];
        $AddonReq = 'N';
        if ($productData->zero_dep == '0' && !$is_liability) {
            $nil_depreciation = '-1';
        } else {
            $nil_depreciation = '0';
        }

        $cpa_cover = 'N';
        $rsa = 'No';
        $consumable = 'N';
        $key_replacement = 'N';
        $engine_protector = 'No';
        $loss_of_belonging = 'N';
        $is_cpa = false;
        $is_zero_dep = false;


        $addon_req = 'Y';
        $tenure = 0;

        if (isset($selected_addons->compulsory_personal_accident[0]['name']) && !$is_od) {
            $is_cpa = true;
            $tenure = 1;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure'])? $selected_addons->compulsory_personal_accident[0]['tenure'] :$tenure;
        }
        if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') {
            $addons = $selected_addons->applicable_addons;
            foreach ($addons as $value) {

                if ($value['name'] == 'Zero Depreciation' /*&& $bike_age <= 5*/ && ($productData->zero_dep == 0)) {
                    $is_zero_dep = true;
                }

                if ($value['name'] == 'Road Side Assistance' /*&& $bike_age <= 5*/) {
                    $rsa = 'Yes';
                }
                if ($value['name'] == 'Engine Protector' /*&& $bike_age <= 5*/) {
                    $engine_protector = 'Yes';
                }
                if($value['name'] == 'Consumable' /*&& $bike_age <= 5*/)
                    {
                        $consumable = 'Y';
                    }

            }
        }

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
        if($masterProduct->product_identifier == 'WITHOUT_ADDONS')
        {
            $rsa = 'No';
            $is_zero_dep = false;
            $engine_protector = 'No';
            $consumable = 'N';
        }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $bifuel = 'false';

        if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if ($value['name'] == 'Electrical Accessories' && !$is_liability) {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'Non-Electrical Accessories' && !$is_liability) {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG' && $is_package) {
                    $type_of_fuel = '5';
                    $bifuel = 'true';
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value['sumInsured'];


                    if ($BiFuelKitSi < 10000 || $BiFuelKitSi > 30000) {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'LPG/CNG cover value should be between 10000 to 30000',
                            'selected value' => $BiFuelKitSi
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
        $IsLLPaidDriver = 'No';

        if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = $selected_addons->additional_covers;
//            print_r($additional_covers);die;
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
                        ];
                    }
                }
                if ($value['name'] == 'LL paid driver' && !$is_od) {
                    $IsLLPaidDriver = 'Yes';
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


        $voluntary_deductable = ((!isset($voluntary_deductable)) ? 0 : $voluntary_deductable);
        $voluntary_deductable_flag = (intval($voluntary_deductable) != 0) ? 'True' : 'False';

        $RegistrationNo = '';
        $PreviousPolExpDt = '';
        $usedCar = 'N';
        $NCBDeclartion = 'N';
        $applicable_ncb = '0';

        $posp_type = 'P';
        $posp_pan = '';

        // ro specific
        $noPreviousData = (!$is_new && $requestData->previous_policy_type == 'Not sure');

        $tp_start_date = '';
        $tp_end_date = '';
        $policy_start_date = Carbon::now();
        if ($requestData->business_type != 'newbusiness') {
            if($is_breakin || $noPreviousData)
            {
                $premium_type_for_dates = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
                if(isset($proposal->prev_policy_expiry_date))
                {
                    $days_diff = get_date_diff('day',$proposal->prev_policy_expiry_date);
                }else
                {
                    $expirydate = Carbon::now()->subDay(31);
                    $days_diff = get_date_diff('day',$expirydate);
                }
                if($premium_type_for_dates == 'breakin' || $premium_type_for_dates == 'own_damage_breakin')
                {
                    if($days_diff <= 30)
                    {
                        $policy_start_date = Carbon::now();
                    }else
                    {
                        $policy_start_date = Carbon::now()->addDay(3); 
                    }
                      
                }else if($premium_type_for_dates == 'third_party_breakin')
                {
                    if($days_diff <= 30)
                    {
                        $policy_start_date = Carbon::now()->addDay(1);
                    }else
                    {
                        $policy_start_date = Carbon::now()->addDay(3); 
                    }
                }
                // $policy_start_date = Carbon::now()->addDay(1);
                $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
                $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
            }
            else
            {
                $policy_start_date = Carbon::parse($proposal->prev_policy_expiry_date)->addDay(1);
                $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
                $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
            }
            $policy_end_date = Carbon::parse($policy_start_date->format('Y-m-d'))->addYear(1)->subDay(1);
            // $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($proposal->prev_policy_expiry_date)));
            // $policy_end_date = date('d/m/Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
        }
        else
        {
            $policy_start_date = Carbon::now();
            $policy_end_date = ($premium_type == 'third_party') ? Carbon::parse($policy_start_date->format('Y-m-d'))->addYear(1)->subDay(1) :Carbon::parse($policy_start_date->format('Y-m-d'))->addYear(5)->subDay(1);
            //$policy_end_date = date('d/m/Y', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            $tp_start_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime($policy_start_date)) : '';
            $tp_end_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime($tp_start_date))))) : '';
        }

        $tp_insurance_company = '';
        $tp_insurance_number = '';
        if ($is_od) {
            $tp_start_date = $proposal->tp_start_date;
            $tp_end_date = $proposal->tp_end_date;
            $tp_insurance_company = $proposal->tp_insurance_company;
            $tp_insurance_number = $proposal->tp_insurance_number;

        } else {
            $tp_start_date =($requestData->business_type == 'newbusiness')? $tp_start_date : date('Y-m-d', strtotime('-1 year', strtotime($proposal->prev_policy_expiry_date)));
            $tp_end_date =($requestData->business_type == 'newbusiness')? $tp_end_date : date('Y-m-d', strtotime('+5 year', strtotime($tp_start_date)));
            $PreviousPolExpDt = date('d-m-Y', strtotime($proposal->prev_policy_expiry_date));
            $PreviousPolStartDt = date('d-m-Y', strtotime('-1 year + 1day', strtotime($proposal->prev_policy_expiry_date)));

        }
        $od_rsd = date('d-m-Y', strtotime('-1 year +1 day', strtotime($proposal->prev_policy_expiry_date)));
        $od_red = date('d-m-Y', strtotime($proposal->prev_policy_expiry_date));
        $NewCar = 'N';
        $RollOver = 'Y';
        $Business_code = 'Roll Over';
        $cpa_cover_period = 1;
        $txt_cover_period = 1;
        $PreviousPolExpDt = date('d-m-Y', strtotime($proposal->prev_policy_expiry_date));
        $PreviousPolStartDt = date('d-m-Y', strtotime('-1 year + 1day', strtotime($proposal->prev_policy_expiry_date)));
        $prev_date = date('d-m-Y', strtotime($proposal->prev_policy_expiry_date));
        $current_date = date('d-m-Y');
        $date1 = date_create($current_date);
        $date2 = date_create($prev_date);
        $diff = date_diff($date1, $date2);
        $days = (int)$diff->format("%R%a");
        $date_diff = abs($days);
        // $policy_start_date = date('d-m-Y');
        $claims_made = $requestData->is_claim;
        if ($new_vehicle) {
            $claims_made = 'Y';
        }
        if ($claims_made == 'N') {
            $is_ncb_apllicable = true;
            $NCBDeclartion = 'Y';
            $yn_claim = 'no';
            $applicable_ncb = $requestData->applicable_ncb;
            $no_claim_bonus = $requestData->previous_ncb;
        } else {
            $is_ncb_apllicable = false;
            $yn_claim = 'yes';
            $no_claim_bonus = 0;
        }
        if($requestData->is_claim == 'Y'){
            $no_claim_bonus = $requestData->previous_ncb;
        }
        $acc_cover_unnamed_passenger = $requestData->unnamed_person_cover_si;
        if ($acc_cover_unnamed_passenger == '25000') {
            $acc_cover_unnamed_passenger = '50000';
        }

        $is_aa_apllicable = false;

//        // COVERS
        $automobile_association_flag = 'False';
        $electrical_flag = (($requestData->electrical_acessories_value != '') ? 'True' : 'False');
        $non_electrical_flag = (($requestData->nonelectrical_acessories_value != '') ? 'True' : 'False');
        $unnamed_passenger_cover_flag = (($requestData->unnamed_person_cover_si != '') ? 'True' : 'False');
        $pa_cover_driver_flag = (($requestData->pa_cover_owner_driver != 'N') ? 'True' : 'False');

        $is_applicable['motor_electric_accessories'] = ((!$is_liability && $IsElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_non_electric_accessories'] = ((!$is_liability && $IsNonElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_lpg_cng_kit'] = (($is_package && ($bifuel == 'true' && $BiFuelKitSi >= 10000 && $BiFuelKitSi <= 30000)) ? true : false);
        $fuel_type = strtoupper($mmv->fuel_type);
        $is_pa_unnamed = ($unnamed_passenger_cover_flag == 'True') ? true : false;
        $is_pa_paid_driver = ($IsLLPaidDriver == 'yes') ? true : false;
        $pa_named = false;

        $product_id = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.BIKE.PRODUCT_ID') : ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.BIKE.PRODUCT_ID_TP') : config('IC.CHOLLA_MANDALAM.V1.BIKE.PRODUCT_ID_OD')));
        $request_data['first_reg_date'] = $requestData->vehicle_register_date;
        $request_data['policy_type'] = ($is_package ? 'Comprehensive' : ($is_liability ? 'Liability' : 'Standalone OD'));
        $request_data['make'] = $mmv->manufacturer;
        $request_data['model'] = $mmv->vehicle_model;
        $request_data['fuel_type'] = $mmv->fuel_type;
        $request_data['cc'] = $mmv->cubic_capacity;
        $request_data['showroom_price'] = $mmv->vehicle_selling_price;
        $request_data['enquiryId'] = $enquiryId;
        $request_data['quote'] = $quote;
        $request_data['company'] = $company_name;
        $request_data['product'] = $product_name;
        $request_data['section'] = 'bike';
        $request_data['proposal_id'] = '';
        $request_data['method'] = 'Token Generation - Quote';
        $request_data['product_id'] = $product_id;
        $request_data['new_vehicle'] = $new_vehicle;

        $request_data['tp_rsd'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($tp_start_date)));
        $request_data['tp_red'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($tp_end_date)));
        $request_data['od_rsd'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($PreviousPolStartDt)));
        $request_data['od_red'] = (($new_vehicle) ? null : (int)($cholla_model->get_excel_date($PreviousPolExpDt)));
        $request_data['productName'] = $productData->product_name;
        $mmv->idv = $proposal->idv;
        $idv = $proposal->idv;
        $token_response = $cholla_model->token_generation($request_data);
        if ($token_response['status'] == 'false') {
            return $token_response;
        }
        $token = $token_response['token'];
//print_r($token);
        $manfyear = explode('-', $proposal->vehicle_manf_year);
        $firstName = $proposal->first_name;

        if (strlen($firstName) <= 3 && !empty($proposal->last_name)) {
            $firstName = $proposal->last_name;
        }
        $quote_array = [
            'date_of_reg' => $cholla_model->get_excel_date($requestData->vehicle_register_date),
            'idv_input' => $idv,
            'ex_show_room' => $request_data['showroom_price'],
            'frm_model_variant' => $request_data['model'] . ' / ' . $request_data['fuel_type'] . ' / ' . $request_data['cc'] . ' CC',
            'make' => $request_data['make'],
            'model_variant' => $request_data['model'],
            'cubic_capacity' => $request_data['cc'],
            'fuel_type' => $request_data['fuel_type'],
            'vehicle_model_code' => $mmv->vehicle_model_code,

            'IMDShortcode_Dev' => config('IC.CHOLLA_MANDALAM.V1.BIKE_IMDSHORTCODE_DEV'),
            'product_id' => $product_id,
            'user_code' => config('IC.CHOLLA_MANDALAM.V1.BIKE_USER_CODE'),
            'intermediary_code' => '',//'2013965725280001',
            'partner_name' => '',

            'Customertype' => ($is_individual ? 'Individual' : 'Corporate'),
            'sel_policy_type' => ($premium_type == 'third_party' ? 'Liability' : ($premium_type == 'own_damage' ? 'Standalone OD' : ($requestData->business_type == 'newbusiness' ? 'Long Term' : 'Comprehensive'))),

            'authorizeChk' => true,
            'no_previous_insurer_chk' => false,

            'first_name' => $firstName,
            'fullName' => ($is_individual ? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name),
            'cus_mobile_no' => $proposal->mobile_number,
            'email_id' => $proposal->email,
            'email' => $proposal->email,
            'phone_no' => $proposal->mobile_number,
            'mobile_no' => $proposal->mobile_number,
            'title' => (is_null($proposal->title) ? "" : $proposal->title),

            'cust_mobile' => $proposal->mobile_number,
            'customer_dob_input' => $cholla_model->get_excel_date($proposal->dob),
            'contract_no' => '',

            'NilDepselected' => ($is_zero_dep ? 'Yes' : 'No'),
            'rsa_cover_app' => $rsa,
            'consumables_cover_app'             => ($consumable=='Y' ? 'Yes' : 'No'),
            'hydrostatic_lock_cover_app' => $engine_protector,
            'hydrostatic_lock_cover' => $engine_protector,

            'pa_cover' => ($is_cpa ? 'Yes' : 'No'),
            'PAAddon' => ($is_cpa ? 'Yes' : 'No'),
            'paid_driver_opted' => $IsLLPaidDriver,
            'unnamed_cover_opted' => ($PAToUnNamedPassenger_IsChecked ? 'Yes' : 'No'),
//            'unnamed_passenger_cover_optional'      => ($is_pa_unnamed ? 'Yes' : 'No'),
            'vehicle_color' => $proposal->vehicle_color,
            'YOM' => $manfyear[1],
            'YOR' => $cholla_model->get_excel_date($requestData->vehicle_register_date),//$cholla_model->get_excel_date('01-01-' . date('Y', strtotime($request_data['first_reg_date']))),
            'prev_exp_date_comp' => (($new_vehicle || $noPreviousData) ? "" : $cholla_model->get_excel_date($PreviousPolExpDt)),
            'prev_insurer_name' => (($new_vehicle || $noPreviousData) ? "" : 'BAJAJ'),
            'prev_policy_no' => ($proposal->previous_policy_number != null ? $proposal->previous_policy_number : ""),
            'place_of_reg' => $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'frm_rto' => $rto_data['state'] . '-' . $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'place_of_reg_short_code' => $rto_data['txt_registration_state_code'],
            'IMDState' => $rto_data['txt_registration_state_code'],
            'city_of_reg' => $rto_data['txt_rto_location_desc'],
            'rto_location_code' => $rto_data['txt_rto_location_code'],
            'no_prev_ins' => (($new_vehicle || $noPreviousData) ? "Yes" : 'No'),
            // 'save_percentage'               => '40%',
            'PrvyrClaim' => $yn_claim,
            'B2B_NCB_App' => '',
            'Lastyrncb_percentage' => '',
            // 'D2C_NCB_PERCENTAGE' => $no_claim_bonus . '%',
            'D2C_NCB_PERCENTAGE' => $requestData->previous_policy_type != 'Third-party'? $no_claim_bonus . '%' : "",
            'engine_no' => (is_null($proposal->engine_number) ? "" : $proposal->engine_number),
            'chassis_no' => $proposal->chassis_number,#($proposal->chassis_no != null ? $proposal->chassis_no : ''),
            'financier_details' => ($proposal->is_vehicle_finance == '1' ? $proposal->name_of_financer : ''),
            'financieraddress' => ($proposal->is_vehicle_finance == '1' ? $proposal->financer_location : ''),
            'hypothecated' => ($proposal->is_vehicle_finance == '1' ? 'Yes' : 'No'),
            'nominee_name' => (is_null($proposal->nominee_name) ? "" : $proposal->nominee_name),
            'nominee_relationship' => (is_null($proposal->nominee_relationship) ? "" : $proposal->nominee_relationship),
            'AgeofNominee'                  => !is_null($proposal->nominee_age) ? $proposal->nominee_age : '0',
            'pan_number' => (is_null($proposal->pan_number) ? "" : $proposal->pan_number),
            'customer_gst_no' => (isset($proposal->gst_number) ? $proposal->gst_number : ''),
            'reg_area' => '',
            'reg_houseno' => '',
            'reg_no' => $requestData->business_type =='newbusiness' ? 'NEW' : $reg_no,
            'reg_street' => '',
            'reg_city' => $proposal->city,
            'reg_state' => $proposal->state,
            'reg_pincode' => $proposal->pincode,
            'reg_toggle' => false,
            'communi_area' => '',
            'communi_houseno' => '',
            'communi_street' => '',
            'commaddress' => ($proposal->is_car_registration_address_same == '1' ? '' : $proposal->car_registration_address1 . ' ' . $proposal->car_registration_address2),
            'communi_city' => ($proposal->is_car_registration_address_same == '1' ? '' : (is_null($proposal->car_registration_city) ? '' : $proposal->car_registration_city)),
            'communi_pincode' => ($proposal->is_car_registration_address_same == '1' ? '' : (is_null($proposal->car_registration_pincode) ? '' : $proposal->car_registration_pincode)),
            'communi_state' => ($proposal->is_car_registration_address_same == '1' ? '' : (is_null($proposal->car_registration_state) ? '' : $proposal->car_registration_state)),
            'address' => $proposal->address_line1 . '|' . $proposal->address_line2 . '|' . $proposal->address_line3,
            'pincode' => $proposal->pincode,
            'city' => $proposal->city,
            'state' => $proposal->state,
            'aadhar' => '',
            'account_no' => '',
            'agree_checbox' => '',
            'b2brto_master_availability' => '',
            'branch_code_sol_id' => '',
            'broker_code' => '',
            'claim_year' => '',
            'cmp_gst_no' => '',
            'covid19_addon' => 'No',
            'covid19_dcb_addon' => 'No',
            'covid19_dcb_benefit' => '',
            'covid19_lossofjob_addon' => 'No',
            'd2cdtd_masterfetched' => '',
            'd2cmodel_master_availability' => '',
            'd2crto_master_availability' => '',
            'emp_code' => '',
            'employee_id' => '',
            'enach_reg' => '',
            'od_prev_insurer_name' => '',
            'od_prev_policy_no' => '',
            'od_red' => '',
            'od_rsd' => '',
            'proposal_id' => '',
            'quote_id' => '',
            'sel_idv' => '',
            'seo_master_availability' => '',
            'seo_policy_type' => '',
            'seo_preferred_time' => '',
            'seo_vehicle_type' => '',
            'sol_id' => '',
            'tp_red' => '',
            'tp_rsd' => '',
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
            'save_percentage' => '',

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

        if($proposal->is_car_registration_address_same != '1')
        {

            $quote_array['address'] = ($proposal->car_registration_address1 ?? '') .' '. ($proposal->car_registration_address2 ?? '');
            $quote_array['city'] = $proposal->car_registration_city ?? '';
            $quote_array['pincode'] = $proposal->car_registration_pincode ?? '';
            $quote_array['state'] = $proposal->car_registration_state ?? '';


            $quote_array['reg_city'] = $proposal->car_registration_city ?? '';
            $quote_array['reg_pincode'] = $proposal->car_registration_pincode ?? '';
            $quote_array['reg_state'] = $proposal->car_registration_state ?? '';

            $quote_array['reg_area'] = '';
            $quote_array['reg_houseno'] = '';
            $quote_array['reg_street'] = '';

            $quote_array['reg_toggle']       = false;


            $quote_array['commaddress'] = $proposal->address_line1 . '|' . $proposal->address_line2 . '|' . $proposal->address_line3;
            $quote_array['communi_pincode'] = $proposal->pincode;
            $quote_array['communi_city'] = $proposal->city;
            $quote_array['communi_state'] = $proposal->state;

            $quote_array['communi_area'] = '';
            $quote_array['communi_houseno'] = '';
            $quote_array['communi_street'] = '';
        }
        
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
        if($is_pos_enabled =='Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000){
            $quote_array['posp_name']=  $pos_data->agent_name;
            $quote_array['POSPcode'] = $pos_data->agent_id;
            $quote_array['POSPPAN']  = $pos_data->pan_no;
            $quote_array['POSPaadhar']  = $pos_data->aadhar_no;
            $quote_array['POSPcontactno'] = $pos_data->agent_mobile;
            $quote_array['posp_direct'] =    '';
        }
        if(config('constants.motorConstant.IS_POS_ENABLED_CHOLLA_TESTING')=='Y' && $quote->idv <= 5000000){
            $quote_array['posp_name']='Ravindra Singh';
            $quote_array['POSPcode']='renewbuy';
            $quote_array['POSPPAN']='DNPPS5548E';
            $quote_array['POSPaadhar']='353938860934';
            $quote_array['POSPcontactno']='9045078061';
            $quote_array['posp_direct']='Delhi';
        }
        if ($is_od) {
            $quote_array['chola_value_added_services'] = 'No';
            $quote_array['daily_cash_allowance'] = 'No';
            $quote_array['emi_entered'] = 0;
            $quote_array['monthly_installment_cover'] = 'No';
            $quote_array['registrationcost'] = 0;
            $quote_array['reinstatement_value_basis'] = 'No';
            $quote_array['return_to_invoice'] = 'No';
            $quote_array['roadtaxpaid'] = 0;
            // $quote_array['rto_location_code']           = '';
            $quote_array['sel_allowance'] = '';
            $quote_array['sel_time_excess'] = 0;
            $quote_array['vehicle_model_code'] = $mmv->vehicle_model_code;
            
            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                $quote_array['od_prev_insurer_name'] = $requestData->previous_insurer;
                $quote_array['od_prev_policy_no'] = $proposal->previous_policy_number; //
                $quote_array['prev_insurer_name'] = $tp_insurance_company; //$proposal->tp_insurance_company
                $quote_array['prev_policy_no'] = $tp_insurance_number; //$proposal->tp_insurance_number
                $quote_array['tp_red'] = $request_data['tp_red'];
                $quote_array['tp_rsd'] = $request_data['tp_rsd'];
            }

            $quote_array['od_red'] = $request_data['od_red'];
            $quote_array['od_rsd'] = $request_data['od_rsd'];
    

        } else if ($requestData->business_type == 'newbusiness') {
            $quote_array['plan_1'] = "Yes";
        }
        if($requestData->business_type == 'newbusiness' && $is_cpa)
        {
            $quote_array['pa_lt_dropdown'] = $tenure;
        }

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $ckycMetaData = json_decode($proposal->ckyc_meta_data);
            $ckycDetails = [
                "CKYC_App_Ref_No" => $proposal->ckyc_reference_id,
                "CKYC_DOB_DOI" => date('d-M-Y', strtotime($proposal->dob)),
                "CKYC_No" => $proposal->ckyc_number,
                "CKYC_PAN_No" => "",
                "CKYC_Aadhar_No" => "",
                "CKYC_DL_No" => "",
                "CKYC_Voter_ID" => "",
                "CKYC_Passport_no" => "",
                "CKYC_CIN" => "",
                "CKYC_KYC_Verified" => "Yes", // Yes Or No
                "CKYC_Mode_of_Verification" => "",
                "CKYC_Status" => "",
                "CKYC_Policy_Gen_Flag" => "",
                "CKYC_Transaction_ID" => $ckycMetaData->transaction_id
            ];
            switch ($proposal->ckyc_type) {
                case 'pan_card':
                    $ckycDetails['CKYC_Mode_of_Verification'] = 'CKYC_PAN';
                    $ckycDetails['CKYC_PAN_No'] = $proposal->ckyc_type_value;
                    break;
                case 'aadhar_card':
                    $ckycDetails['CKYC_Mode_of_Verification'] = 'CKYC_AADHAR';
                    $ckycDetails['CKYC_Aadhar_No'] = $proposal->ckyc_type_value;
                    break;
            }
            $quote_array = array_merge($quote_array, $ckycDetails);
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
            'type' => 'request',
            'transaction_type' => 'proposal',
        ];

        $quote_url = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.BIKE.END_POINT_URL_QUOTE') : ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.BIKE.END_POINT_URL_QUOTE_TP') : config('IC.CHOLLA_MANDALAM.V1.BIKE.END_POINT_URL_QUOTE_OD')));
        $get_response = getWsData(
            $quote_url,
            $quote_array,
            'cholla_mandalam',
            $additional_data

        );
        $data = $get_response['response'];

        $quote_response = json_decode($data, true);
//        print_r(json_encode($quote_response));
        if ($quote_response != null) {
            $error_message = $quote_response['Message'];
            if ($quote_response['Status'] != 'success') {
                return [
                    'status' => false,
                    'message' => $error_message,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'return_data' => [
                        'premium_request' => $quote_array,
                        'premium_response' => $quote_response,
                    ],
                ];
            } else {
                $quote_response = array_change_key_case_recursive($quote_response);

                $quote_response_data = $quote_response['data'];

                $base_cover['od'] = $quote_response_data['basic_own_damage_cng_elec_non_elec'];
                $base_cover['electrical'] = $quote_response_data['electrical_accessory_prem'];
                $base_cover['non_electrical'] = $quote_response_data['non_electrical_accessory_prem'];
                $base_cover['lpg_cng_od'] = $quote_response_data['cng_lpg_own_damage'];


                $base_cover['tp'] = $quote_response_data['basic_third_party_premium'];
                $base_cover['pa_owner'] = $quote_response_data['personal_accident'];
                $base_cover['unnamed'] = $quote_response_data['unnamed_passenger_cover'];
                $base_cover['paid_driver'] = 0;
                $base_cover['legal_liability'] = $quote_response_data['legal_liability_to_paid_driver'];
                $base_cover['lpg_cng_tp'] = $quote_response_data['cng_lpg_tp'];


                $base_cover['ncb'] = $quote_response_data['no_claim_bonus'];
                $base_cover['automobile_association'] = '0';
                $base_cover['anti_theft'] = '0';
                $base_cover['other'] = $quote_response_data['dtd_discounts'] + $quote_response_data['gst_discounts'];


                $base_cover['zero_dep'] = $quote_response_data['zero_depreciation'];
                $base_cover['key_replacement'] = $quote_response_data['key_replacement_cover'];
                $base_cover['consumable'] = $quote_response_data['consumables_cover'];
                $base_cover['loss_of_belongings'] = $quote_response_data['personal_belonging_cover'];
                $base_cover['rsa'] = $quote_response_data['rsa_cover'];
                $base_cover['engine_protect'] = $quote_response_data['hydrostatic_lock_cover'];
                $base_cover['tyre_secure'] = 'NA';
                $base_cover['return_to_invoice'] = 'NA';
                $base_cover['ncb_protect'] = 'NA';

                $total_premium_amount = $quote_response_data['total_premium'];

                $total_basic_od_premium = $base_cover['od']
                    + $base_cover['electrical']
                    + $base_cover['non_electrical']
                    + $base_cover['lpg_cng_od'];

                $total_tp_premium = $base_cover['tp']
                    + $base_cover['pa_owner']
                    + $base_cover['unnamed']
                    + $base_cover['paid_driver']
                    + $base_cover['legal_liability']
                    + $base_cover['lpg_cng_tp'];
                $total_discount = $base_cover['ncb']
                    + $base_cover['automobile_association']
                    + $base_cover['anti_theft']
                    + $base_cover['other'];

                $addon_sum = (is_integer($base_cover['zero_dep']) ? $base_cover['zero_dep'] : 0)
                    + (is_integer($base_cover['key_replacement']) ? $base_cover['key_replacement'] : 0)
                    + (is_integer($base_cover['consumable']) ? $base_cover['consumable'] : 0)
                    + (is_integer($base_cover['loss_of_belongings']) ? $base_cover['loss_of_belongings'] : 0)
                    + (is_integer($base_cover['rsa']) ? $base_cover['rsa'] : 0)
                    + (is_integer($base_cover['engine_protect']) ? $base_cover['engine_protect'] : 0)
                    + (is_integer($base_cover['tyre_secure']) ? $base_cover['tyre_secure'] : 0)
                    + (is_integer($base_cover['return_to_invoice']) ? $base_cover['return_to_invoice'] : 0)
                    + (is_integer($base_cover['ncb_protect']) ? $base_cover['ncb_protect'] : 0);


                $tax = $quote_response_data['gst'];


                $premium_data['policy_id'] = $quote_response_data['policy_id'];
                $premium_data['proposal_id'] = $quote_response_data['proposal_id'];
                $premium_data['quote_id'] = $quote_response_data['quote_id'];
                $premium_data['token'] = $token;

                $premium_data['premium_breakup'] = [
                    'total_od' => ($premium_type == 'third_party') ? 0 : round($total_basic_od_premium),
                    'total_tp' => $total_tp_premium,
                    'total_discount' => $total_discount,
                    'total_addon' => $addon_sum,
                    'cpa' => $base_cover['pa_owner'],
                    'total_premium' => $total_premium_amount
                ];


                // ro specific end

//                if (isset($prev_proposal_id)) {
//                    $agent_data = get_details('motor_agent_proposal', ['*'], ['motor_proposal_id' => base64_decode($prev_proposal_id), 'company_name' => 'CHOLLA_MANDALAM']);
//                    if (!empty($agent_data)) {
//                        $POS_data = get_agent_data($agent_data[0]['agent_id'], 'cholla_mandalam');
//                        if (!empty($POS_data)) {
//                            $posp_type = 'P';
//                            $posp_pan = $POS_data['pan_no'];
//                        }
//                    }
//                }else if (is_agent_logged_in()) {
//                    $POS_data = get_agent_data($this->session->userdata('agent_id'), 'cholla_mandalam');
//                    if (!empty($POS_data)) {
//                        $posp_type = 'P';
//                        $posp_pan = $POS_data['pan_no'];
//                    }
//                }


                $cpa_cover_flag = '0';

                if ($requestData->vehicle_owner_type == 'I') {
                    $client_type = 'I';
                    $dob = date('d/m/Y', strtotime($proposal->dob));
                    if (!$is_od) {
                        $cpa_cover_flag = '-1';
                    }
                    if ($gender == 'M') {
                        $salutation = 'Mr';
                    } else {
                        $salutation = 'Ms';
                    }
                    $cust_name = $proposal->first_name . ' ' . $proposal->last_name;
                    $cpa_nom_age_det = 'Y';
                    $cpa_nom_perc = '100';
                } else {
                    $client_type = 'C';
                    $occupation = 'SVCM';
                    $Capital = '1';
                    $dob = '';
                    $gender = '';
                    $salutation = 'M/S';

                    $cust_name = $proposal->first_name . '(' . $proposal->last_name . ')';

                    $cpa_nom_name = '';
                    $cpa_nom_age = '';
                    $cpa_nom_age_det = '';
                    $cpa_nom_perc = '';
                    $cpa_relation = '';
                    $cpa_appointee_name = '';
                    $cpa_appointe_rel = '';
                }

//                $quote_array                                    = $premium_request;
                $quote_array['quote_id'] = $premium_data['quote_id'];
                $quote_array['proposal_id'] = $premium_data['proposal_id'];
                $quote_array['policy_id'] = $premium_data['policy_id'];
                $token = $premium_data['token'];

                $request_data['first_reg_date'] = $requestData->vehicle_register_date;
                $request_data['policy_type'] = ($is_package ? 'Comprehensive' : ($is_liability ? 'Third Party' : 'Long Term'));
                $request_data['rto_location_code'] = $rto_data['txt_rto_location_code'];
                $additional_data_proposal = [
                    'requestMethod' => 'post',
                    'Authorization' => $token,
                    'proposal_id' => '0',
                    'enquiryId' => $request_data['enquiryId'],
                    'method' => 'Proposal Submition - Proposal',
                    'section' => $request_data['section'],
                    'type' => 'request',
                    'transaction_type' => 'proposal',
                ];
                $get_response = getWsData(
                    config('IC.CHOLLA_MANDALAM.V1.BIKE.END_POINT_URL_PROPOSAL'),
                    $quote_array,
                    'cholla_mandalam',
                    $additional_data_proposal

                );
                $proposaldata = $get_response['response'];
                if ($proposaldata) {
                    $proposal_response = json_decode($proposaldata, true);
                    $error_message = $proposal_response['Message'];
                    if ($proposal_response['Status'] != 'success') {
                        return [
                            'status' => false,
                            'message' => $error_message,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'return_data' => [
                                'premium_request' => $quote_array,
                                'premium_response' => $proposal_response,
                            ],
                        ];
                    } else {

                        $proposal_response = array_change_key_case_recursive($proposal_response);

                        $proposal_response_data = $proposal_response['data'];
                        $payment_id = $proposal_response_data['payment_id'];
                        $total_premium = $proposal_response_data['total_premium'];
                        $service_tax_total = $proposal_response_data['gst'];
                        $base_premium = $proposal_response_data['net_premium'];
                        //premium calculation
                        $base_cover['od'] = $quote_response_data['basic_own_damage_cng_elec_non_elec'];
                        $base_cover['electrical'] = $quote_response_data['electrical_accessory_prem'];
                        $base_cover['non_electrical'] = $quote_response_data['non_electrical_accessory_prem'];
                        $base_cover['lpg_cng_od'] = $quote_response_data['cng_lpg_own_damage'];

                        $base_cover['tp'] = $quote_response_data['basic_third_party_premium'];
                        $base_cover['pa_owner'] = $quote_response_data['personal_accident'];
                        $base_cover['unnamed'] = $quote_response_data['unnamed_passenger_cover'];
                        $base_cover['paid_driver'] = '0';
                        $base_cover['legal_liability'] = $quote_response_data['legal_liability_to_paid_driver'];
                        $base_cover['lpg_cng_tp'] = $quote_response_data['cng_lpg_tp'];

                        $base_cover['ncb'] = $quote_response_data['no_claim_bonus'];
                        $base_cover['automobile_association'] = '0';
                        $base_cover['anti_theft'] = '0';
                        $base_cover['other_discount'] = $quote_response_data['dtd_discounts'] + $quote_response_data['gst_discounts'];

                        $addon['zero_dep'] = (($quote_response_data['zero_depreciation'] == '0') ? 'NA' : $quote_response_data['zero_depreciation']);
                        $addon['key_replacement'] = (($quote_response_data['key_replacement_cover'] == '0') ? 'NA' : $quote_response_data['key_replacement_cover']);
                        $addon['consumable'] = (($quote_response_data['consumables_cover'] == '0') ? 'NA' : $quote_response_data['consumables_cover']);
                        $addon['loss_of_belongings'] = (($quote_response_data['personal_belonging_cover'] == '0') ? 'NA' : $quote_response_data['personal_belonging_cover']);
                        $addon['rsa'] = (($quote_response_data['rsa_cover'] == '0') ? 'NA' : $quote_response_data['rsa_cover']);
                        $addon['engine_protect'] = (($quote_response_data['hydrostatic_lock_cover'] == '0') ? 'NA' : $quote_response_data['hydrostatic_lock_cover']);
                        $addon['tyre_secure'] = 'NA';
                        $addon['return_to_invoice'] = 'NA';
                        $addon['ncb_protect'] = 'NA';
                        $total_premium_amount = $quote_response_data['total_premium'];

                        $base_cover['tp'] = $base_cover['tp'];// + $base_cover['legal_liability'];
                        if (($addon['zero_dep'] == 'NA') && $is_zero_dep) {
                            return [
                                'premium_amount' => 0,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'message' => 'Zero dep value issue',
                                'car_age' => $bike_age,
                                'reg_date' => $requestData->vehicle_register_date
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
                        } elseif ($masterProduct->product_identifier == 'ZERO_DEP') {
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
                        } elseif ($masterProduct->product_identifier == 'BASIC') {
                            $add_ons_data = [
                                'in_built'   => [],
                                'additional' => [],
                                'other'      => [],
                            ];
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
                        $total_tp = $base_cover['tp'] + $base_cover['legal_liability'] + $base_cover['unnamed'] + $base_cover['lpg_cng_tp'] + $base_cover['pa_owner'];

                        $total_discount = $base_cover['other_discount'] + $base_cover['automobile_association'] + $base_cover['anti_theft'] + $base_cover['ncb'];
                        
                        $total_od_amount = $total_od - $total_discount;
//print_r($base_cover['pa_owner'].'-----');
//                        print_r($addon_sum.'///');
//print_r($total_od.'--'.$total_tp.'--'.$total_discount.'--'.$addon_sum);

                        $basePremium = $total_od + $total_tp - $total_discount + $addon_sum;

                        $totalTax = $basePremium * 0.18;

                        $final_premium = $basePremium + $totalTax;

                        // $policy_start_date = date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date)));
                        // $policy_end_date = date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date)));
                        $pg_transaction_id = date('Ymd') . time();


                        $premium_data['premium_breakup'] = [
                            'total_od' => ($premium_type == 'third_party') ? 0 : round($total_basic_od_premium),
                            'total_tp' => $total_tp_premium,
                            'total_discount' => $total_discount,
                            'total_addon' => $addon_sum,
                            'cpa' => $base_cover['pa_owner'],
                            'total_premium' => $total_premium_amount
                        ];

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'proposal_no' => $payment_id,
                                //'unique_proposal_id' => $payment_id,
                                'policy_start_date' => $policy_start_date->format('d-m-Y'),
                                'policy_end_date' =>  $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ?  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-'))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?   date('d-m-Y', strtotime(strtr($policy_start_date . ' + 5 year - 1 days', '/', '-'))):  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-')))),
                                'od_premium' => round($total_od_amount),
                                'tp_premium' => round($total_tp),
                                'total_premium' => round($basePremium),
                                'addon_premium' => round($addon_sum),
                                'cpa_premium' => $base_cover['pa_owner'],
                                'service_tax_amount' => round($totalTax),
                                'total_discount' => round($total_discount),
                                'final_premium'=>env('APP_ENV') == 'local' ? config('constants.IcConstants.cholla_madalam.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM') : $final_premium,
                                'final_payable_amount' => $proposal_response_data['total_premium'],#round($final_premium),
                                'ic_vehicle_details' => '',
                                'discount_percent' => $no_claim_bonus . '%',
                                'vehicale_registration_number' => $proposal->vehicale_registration_number,
                                'engine_no' => $proposal->engine_number,
                                'chassis_no' => $proposal->chassis_number,
                                'product_code' => $proposal->product_code,
                                'ncb_discount' => $base_cover['ncb'],
                                'dob' => ($proposal->dob != null ? date("Y-m-d", strtotime($proposal->dob)) : ''),
                                'nominee_dob' => ($proposal->nominee_dob != null ? date("Y-m-d", strtotime($proposal->nominee_dob)) : ''),
                                'cpa_policy_fm_dt' => ($proposal->cpa_policy_fm_dt != null ? date("Y-m-d", strtotime($proposal->cpa_policy_fm_dt)) : ''),
                                'cpa_policy_to_dt' => ($proposal->cpa_policy_to_dt != null ? date("Y-m-d", strtotime($proposal->cpa_policy_to_dt)) : ''),
                                'cpa_policy_no' => $proposal->cpa_policy_no,
                                'cpa_sum_insured' => $proposal->cpa_sum_insured,
                                'car_ownership' => $proposal->car_ownership,
                                'electrical_accessories' => $proposal->electrical_accessories,
                                'non_electrical_accessories' => $proposal->non_electrical_accessories,
                                'version_no' => $proposal->version_no,
                                'vehicle_category' => $proposal->vehicle_category,
                                'vehicle_usage_type' => $proposal->vehicle_usage_type,
                                'tp_start_date' => $tp_start_date,
                                'tp_end_date' => $tp_end_date,
                                'tp_insurance_company' => $tp_insurance_company,
                                'tp_insurance_number' => $tp_insurance_number,
                            ]);


                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

                        $proposal_data = UserProposal::find($proposal->user_proposal_id);

                        ChollaMandalamPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        return [
                            'status' => true,
                            'message' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'proposalNo' => $payment_id,
                                'userProductJourneyId' => $proposal_data->user_product_journey_id,
                            ],
                            'base_cover' => $base_cover
                        ];


                    }
                } else {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                    ];
                }
            }
        } else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                'error' => 'no response form service'
            ];
        }

    }
}
