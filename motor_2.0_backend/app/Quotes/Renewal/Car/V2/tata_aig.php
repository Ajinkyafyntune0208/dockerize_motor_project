<?php

use App\Models\MasterPremiumType;
use Carbon\Carbon;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use App\Models\MasterProduct;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getTataRenewalQuote($enquiryId, $requestData, $productData)
{
    $mmv = get_mmv_details($productData, $requestData->version_id, 'tata_aig');
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ]
        ];
    }
    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle not mapped',
            ]
        ];
    } elseif ($mmv->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }



    $mmv_data = [
        'manf_name'     => $mmv->manufacturer,
        'model_name'    => $mmv->vehiclemodel,
        'version_name'  => $mmv->txt_varient,
        'fuel_type'     => $mmv->txt_fuel,
        'seating_capacity' => $mmv->seatingcapacity,
        'carrying_capacity' => $mmv->carryingcapacity,
        'cubic_capacity' => $mmv->cubiccapacity,
        'gross_vehicle_weight' => $mmv->grossvehicleweight ?? null,
        'vehicle_type'  => "Private Car",
    ];

    $prev_policy_end_date = $requestData->previous_policy_expiry_date;
    $date1 = !empty($requestData->vehicle_invoice_date) ? new DateTime($requestData->vehicle_invoice_date) : new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($prev_policy_end_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = floor($age / 12);
    $businessType = 'Roll Over';

    $premiumType =MasterPremiumType::
    where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();


    $is_package     = (($premiumType == 'comprehensive' || $premiumType == 'breakin') ? true : false);
    $is_liability   = (($premiumType == 'third_party' || $premiumType == 'third_party_breakin') ? true : false);
    $is_od          = (($premiumType == 'own_damage' || $premiumType == 'own_damage_breakin') ? true : false);
    $is_indivisual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
    $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);


    $additional_data = [
        'enquiryId'         => $enquiryId,
        'headers'           => [],
        'requestMethod'     => 'post',
        'requestType'       => 'json',
        'section'           => $productData->product_sub_type_code,
        'method'            => 'Token Generation',
        'transaction_type'  => 'quote',
        'productName'       => $productData->product_name,
        'type'              => 'token'
    ];


    $tokenRequest = [
        'grant_type'    => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_GRANT_TYPE_RENEWAL'),
        'scope'         => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_SCOPE_RENEWAL'),
        'client_id'     => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_ID_RENEWAL'),
        'client_secret' => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_SECRET_RENEWAL'),
    ];


    $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_TOKEN'), $tokenRequest, 'tata_aig_v2', $additional_data);
    $tokenResponse = $get_response['response'];


    if ($tokenResponse && $tokenResponse != '' && $tokenResponse != null) {

        $tokenResponse = json_decode($tokenResponse, true);

        $tokenResponse = $tokenResponse['access_token'];
    }


    $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
    $policynumber = str_replace('-00', '',$user_proposal->previous_policy_number);
    $requestdata = [
        "PolicyNumber" => $policynumber,
        "lobname" => "PrivateCarPolicyInsurance",
        "RegNumber" => ""
    ];



    $fetch_url = config('IC.TATA_AIG.V2.CAR.END_POINT_URL_RENEWAL'); //https://uatapigw.tataaig.com/pc-motor-renewal/v1/modify

    $additional_data = [
        'enquiryId'         => $enquiryId,
        'requestMethod'     => 'post',
        'productName'       => $productData->product_name,
        'company'           => 'tata_aig',
        'section'           => $productData->product_sub_type_code,
        'method'            => 'Fetch Policy Details - Renewal',
        'transaction_type'  => 'quote',
        'type'              => 'renewal',
        'headers' => [
            'Content-Type'   => "application/json",
            'Connection'     => "Keep-Alive",
            'Authorization'  =>  'Bearer ' . $tokenResponse,
            'Accept'         => "application/json",
            'x-api-key'      =>   config("IC.TATA_AIG.V2.CAR.FETCH_KEY_ID_RENEWAL"),


        ]
    ];

    $get_response = getWsData($fetch_url, $requestdata, 'tata_aig_v2', $additional_data);
    $get_response['response'] = json_decode($get_response['response']);
    $data = $get_response['response'];
    $response_data = $data;

    if ($response_data->message_txt == "Success"  && $response_data->status == '200') {

        $allowedPlans = [
            'SAPPHIRE ++' => 'SAPPHIRE++',
            'SILVER'      => 'SILVER',
            'GOLD'         => 'GOLD',
            'PEARL'        => 'PEARL',
            'PEARL +'      => 'PEARL+',
            'PEARL++'      => 'PEARL++',
            'CORAL'        => 'CORAL',
            'PLATINUM'     => 'PLATINUM',
            'SAPPHIRE'     => 'SAPPHIRE',
            'SAPPHIREPLUS' => 'SAPPHIREPLUS',
    
        ];
    
        $prevAddOnPlan = $response_data->data->current_plan->bundleName;
    
        if($prevAddOnPlan !='NIL')
        {
        if (isset($allowedPlans[trim($prevAddOnPlan)])) {
            $prevAddOnPlan = $allowedPlans[trim($prevAddOnPlan)];
            if ($productData->product_identifier != $prevAddOnPlan) {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => 'QUOTES ARE NOT ALLOWED FOR THIS PRODUCT'
                ];
            }
        }
        }
        elseif($prevAddOnPlan === 'NIL' && $response_data->data->txt_previous_policy_plan === 'Standalone TP (1 year)' )
        {
            $productData->product_identifier = trim($productData->product_identifier);
            if (in_array($productData->product_identifier, $allowedPlans, true)) {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => 'QUOTES ARE NOT ALLOWED FOR THIS PRODUCT'
                ];
            }
            
        }

        
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);

        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $compulsory_personal_accident = ($selected_addons->compulsory_personal_accident == null ? [] : $selected_addons->compulsory_personal_accident);

        $pa_owner  =  $ll_paid_driver =$is_tppd =  $is_Geographical =   $PAforaddionaldPaidDriver =  $is_pa_paid = 0;
        $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI =  $ElectricalaccessSI = $NonElectricalaccessSI = '';
        $PAforUnnamedPassenger  =  $Electricalaccess = $NonElectricalaccess = $externalCNGKIT = $externalCNGKITSI = $is_anti_theft=  "false" ; 
        $countries = [];

        $voluntary_excess_amt = '';

        $is_electrical = false;
        $is_non_electrical = false;
        $is_lpg_cng = false;
    
        foreach ($accessories as $key => $value)
        {
            if (in_array('Electrical Accessories', $value) && !$is_liability)
            {
                $Electricalaccess = "Yes";
                $ElectricalaccessSI = $value['sumInsured'];
                $is_electrical = true;
            }
    
            elseif (in_array('Non-Electrical Accessories', $value) && !$is_liability)
            {
                $NonElectricalaccess = "Yes";
                $NonElectricalaccessSI = $value['sumInsured'];
                $is_non_electrical = true;
            }
    
            elseif (in_array('External Bi-Fuel Kit CNG/LPG', $value))
            {
                $externalCNGKIT = "Yes";
                $externalCNGKITSI = $value['sumInsured'];
                $is_lpg_cng = true;
                if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                    $mmv->txt_fuel = 'External CNG';
                    $mmv->txt_fuelcode = '5';
                }
            }
    
            elseif (in_array('PA To PaidDriver Conductor Cleaner', $value))
            {
                $PAPaidDriverConductorCleaner = "Yes";
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }

        foreach ($discounts as $key => $data)
        {
            if ($data['name'] == 'anti-theft device' && !$is_liability)
            {
                $is_anti_theft = true;
                $is_anti_theft_device_certified_by_arai = 'true';
            }
    
            elseif ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured']) && !$is_liability)
            {
                $is_voluntary_access = true;
                $voluntary_excess_amt = $data['sumInsured'];
            }
    
            elseif ($data['name'] == 'TPPD Cover' && !$is_od)
            {
                $is_tppd = true;
                $tppd_amt = '9999';
            }
        }


        foreach ($additional_covers  as $key => $value) {
            if (in_array('LL paid driver', $value)) {

                $llpaidDriverSI = $value['sumInsured'];
                $ll_paid_driver = true;
            }

           
            elseif (in_array('Unnamed Passenger PA Cover', $value))
            {
                $PAforUnnamedPassenger = "true";
                $PAforUnnamedPassengerSI = $value['sumInsured'];
                $is_pa_unnamed = true;
            }

            elseif (in_array('PA cover for additional paid driver', $value)) {
                $PAforaddionaldPaidDriver = "true";
                $PAforaddionaldPaidDriverSI = (string) $value['sumInsured'];
                $is_pa_paid = "true";
            }

            elseif ($value['name'] == 'Geographical Extension') {
                $countries = $value['countries'] ;
                $is_Geographical = "true";
            }

        }

        foreach ($compulsory_personal_accident  as $key => $value) {
            if (in_array('Compulsory Personal Accident', $value)) {
                $pa_owner = true;
            }
        }

        if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null)
        {
            $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
        }
        else
        {
            $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
        }

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $policynumber = str_replace('-00', '',$user_proposal->previous_policy_number);
        $requestdata = [
            "__finalize" => "1",
            "PolicyNumber" => $policynumber,
            "registration_no" => "",
            "pa_owner" => $pa_owner == true && ($is_individual && !$is_od && !$is_liability) ? "Yes" : "",
            'pa_owner_declaration'          => 'None',
            'pa_owner_tenure'               =>  $pa_owner == true && ($is_individual && !$is_od && !$is_liability) ? '1' : '',
            'vehicle_idv' => "",
            "tppd_discount" => $is_tppd == true ? "true" : "false", 
            "pa_unnamed" => "false",
            "pa_unnamed_no" => "",
            "pa_unnamed_si" => "",
            "voluntary_amount" => (string)($voluntary_excess_amt) ?? "false",
            "antitheft_cover" => $is_anti_theft ,
            "geography_extension"=> $is_Geographical ? "true" : "false",
            "geography_extension_bang" => in_array('Bangladesh', $countries) ? "true" : "false",
            "geography_extension_bhutan" => in_array('Bhutan', $countries) ? "true" : "false",
            "geography_extension_lanka" => in_array('Sri Lanka', $countries) ? "true" : "false",
            "geography_extension_maldives" => in_array('Nepal', $countries) ? "true" : "false" ,
            "geography_extension_nepal" =>  in_array('Maldives', $countries) ? "true" : "false" ,
            "geography_extension_pak" => in_array('Pakistan', $countries) ? "true" : "false",
            "pa_paid" => $is_pa_paid ==true ? "true" :"false",
            "pa_paid_no" =>  "1",
            "pa_paid_si" => $PAforaddionaldPaidDriverSI,


            'pa_unnamed' => $PAforUnnamedPassenger,
            'pa_unnamed_csi' => '',
            'pa_unnamed_no' => (string)($mmv->seatingcapacity),
            'pa_unnamed_si' => (string)$PAforUnnamedPassengerSI,


            'electrical_si'                 => (string)($ElectricalaccessSI),
            "electrical_cover"               => $Electricalaccess,
            "electrical_desc"                 => "",
    
    
            'non_electrical_si'             => (string)($NonElectricalaccessSI),
            "non_electrical_cover"          => $NonElectricalaccess, 
            "non_electrical_desc"           => "",
        

            
            'cng_lpg_cover'                 => (string)($externalCNGKIT),
            'cng_lpg_si'                    => ($is_liability ? '0' : (string)($externalCNGKITSI)),
            "regno_1"                       => $vehicle_register_no[0] ?? "",
            "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")),
            "regno_3"                       => $vehicle_register_no[2] ?? "",
            "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),

        ];

        $fetch_url = config('IC.TATA_AIG.V2.CAR.END_POINT_URL_RENEWAL_QUOTE'); //https://uatapigw.tataaig.com/pc-motor-renewal/v2/quote
        $data = $requestdata;
        $checksum_data =checksum_encrypt($data);

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Calculation Renewal',
            'transaction_type'  => 'quote',
            'checksum'          => $checksum_data,
            'type'              => 'renewal',
            'headers' => [
                'Content-Type'   => "application/json",
                'Connection'     => "Keep-Alive",
                'Authorization'  =>  'Bearer ' . $tokenResponse,
                'Accept'         => "application/json",
                'x-api-key'      =>   config("IC.TATA_AIG.V2.CAR.FETCH_KEY_ID_RENEWAL"),


            ]
        ];

        $get_response = getWsData($fetch_url, $requestdata, 'tata_aig_v2', $additional_data);

        $get_response['response'] = json_decode($get_response['response']);

        $data = $get_response['response'];


        $max_idv    = $is_liability ? 0 : $data->data->max_idv;
        $min_idv    = $is_liability ? 0 : $data->data->min_idv;
        $idv        = $is_liability ? 0 : $data->data->vehicle_idv;      

        $skip_second_call = false;
        if ($requestData->is_idv_changed == "Y") {
            if ($requestData->edit_idv >= $max_idv) {
                $idv = (int) floor($max_idv);
                $requestdata['vehicle_idv'] = $idv;
            } else if ($requestData->edit_idv <= $min_idv) {
                $idv = (int) ceil($min_idv);
                $requestdata['vehicle_idv'] = $idv;
            } else {
                $idv = ($requestData->edit_idv);
                $requestdata['vehicle_idv'] = $idv;
            }
        } else {

           
            $skip_second_call = true;
            $idv = $is_liability ? 0 : $idv;
        
        }


    
        $requestdata['__finalize'] = '1';    
        $data = $requestdata;
        $checksum_data = checksum_encrypt($data);
        $fetch_url = config('IC.TATA_AIG.V2.CAR.END_POINT_URL_RENEWAL_QUOTE'); //https://uatapigw.tataaig.com/pc-motor-renewal/v2/quote

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Re-Calculation Renewal',
            'transaction_type'  => 'quote',
            'checksum'          => $checksum_data,
            'type'              => 'renewal',
            'headers' => [
                'Content-Type'   => "application/json",
                'Connection'     => "Keep-Alive",
                'Authorization'  =>  'Bearer ' . $tokenResponse,
                'Accept'         => "application/json",
                'x-api-key'      =>   config("IC.TATA_AIG.V2.CAR.FETCH_KEY_ID_RENEWAL"),


            ]
        ]; 

        if(!$skip_second_call) {

            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'tata_aig', $checksum_data, 'CAR');
            if($is_data_exits_for_checksum['found'] && $refer_webservice) {
                $get_response = $is_data_exits_for_checksum;
                $get_response['response'] = json_decode($get_response['response']);
            } else {
                $get_response = getWsData($fetch_url, $requestdata, 'tata_aig_v2', $additional_data);
                $get_response['response'] = json_decode($get_response['response']);
            }
        }
        
        $data = $get_response['response']; 
        if ($data->status != '200') {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $data->message_txt ?? 'Insurer Not Reachable.'
            ];
        }  
        $premium_lists = $data->data->premiumSchemes[0]->premium_break_up;


        $igst                   = $anti_theft = $other_discount = $tp_gio = $od_gio  =
        $rsapremium             = $pa_paid_driver = $zero_dep_amount =
        $ncb_discount           = $tppd = $final_tp_premium = $antitheft_discount = 
        $final_od_premium       = $final_net_premium =
        $final_payable_amount   = $basic_od = $electrical_accessories =
        $lpg_cng_tp             = $lpg_cng = $non_electrical_accessories = $tppd_discount =
        $pa_owner               = $voluntary_excess = $pa_unnamed =
        $engine_protection = $consumables_cover = $return_to_invoice = $pa_paid  =  $pa_unnamed = 0;
        $final_gst_amount       = $vehicle_in_90_days =  $Basic_TP_Premium = $return_to_invoice = $zero_dep_amount = $ncb_protect_amount = $key_replacment_amount = $personal_belongings_amount = $engine_seccure_amount = $tyre_secure_amount = $counsumable_amount =  $repair_glass_prem = $rsa_amount = $emergency_expense_amount =  $totalAddons = 0;



        if (!empty($premium_lists->total_od_premium->od->basic_od)) {

            $basic_od = $premium_lists->total_od_premium->od->basic_od;
        }

        if (!empty($premium_lists->total_od_premium->od->geography_extension_od_prem)) {

            $od_gio  = $premium_lists->total_od_premium->od->geography_extension_od_prem;
        }

        if (!empty($premium_lists->total_tp_premium->geography_extension_tp_prem)) {

            $tp_gio  = $premium_lists->total_tp_premium->geography_extension_tp_prem;
        }

        if (!empty($premium_lists->total_od_premium->discount_od->ncb_prem)) {

            $ncb_discount = $premium_lists->total_od_premium->discount_od->ncb_prem;
        }

        if (!empty($premium_lists->total_od_premium->discount_od->atd_disc_prem)) {

            $antitheft_discount = $premium_lists->total_od_premium->discount_od->atd_disc_prem;
        }

        if (!empty($premium_lists->total_od_premium->total_od)) {
            $OD_Total = $premium_lists->total_od_premium->total_od;
        }

        if (!empty($premium_lists->total_tp_premium->basic_tp)) {
            $Basic_TP_Premium = $premium_lists->total_tp_premium->basic_tp;
        }

        if (!empty($premium_lists->total_tp_premium->pa_owner_prem)) {

            $pa_owner = $premium_lists->total_tp_premium->pa_owner_prem;
        }

        if (!empty($premium_lists->total_tp_premium->ll_paid_prem)) {
            $Legal_Liability_To_Employees = $premium_lists->total_tp_premium->ll_paid_prem;
        }

        if (!empty($premium_lists->total_tp_premium->total_tp)) {
            $final_tp_premium = $premium_lists->total_tp_premium->total_tp;
        }

        if (!empty($premium_lists->net_premium)) {
            $final_net_premium = $premium_lists->net_premium;
        }

        if (!empty($premium_lists->final_premium)) {
            $final_payable_amount = $premium_lists->final_premium;
        }

        if (!empty($premium_lists->total_od_premium->od->electrical_prem)) {
            $electrical_accessories = $premium_lists->total_od_premium->od->electrical_prem;
        }

        if (!empty($premium_lists->total_od_premium->od->non_electrical_prem)) {
            $non_electrical_accessories = $premium_lists->total_od_premium->od->non_electrical_prem;
        }    

        if (!empty($premium_lists->total_od_premium->od->cng_lpg_od_prem)) {
            $lpg_cng = $premium_lists->total_od_premium->od->cng_lpg_od_prem;
        }


        if (!empty($premium_lists->total_tp_premium->ll_paid_prem) && $ll_paid_driver !== 0) {

            $ll_paid_driver = $premium_lists->total_tp_premium->ll_paid_prem;
        }

        if (!empty($premium_lists->total_addOns->dep_reimburse_prem)) {

            $zero_dep_amount = $premium_lists->total_addOns->dep_reimburse_prem;
        }

        if (!empty($premium_lists->total_addOns->return_invoice_prem)) {

            $return_to_invoice = $premium_lists->total_addOns->return_invoice_prem;
        }

        if (!empty($premium_lists->total_addOns->ncb_protection_prem)) {

            $ncb_protect_amount = $premium_lists->total_addOns->ncb_protection_prem;
        }

        if (!empty($premium_lists->total_addOns->personal_loss_prem)) {

            $personal_belongings_amount = $premium_lists->total_addOns->personal_loss_prem;
        }

        if (!empty($premium_lists->total_addOns->key_replace_prem)) {

            $key_replacment_amount = $premium_lists->total_addOns->key_replace_prem;
        }

        if (!empty($premium_lists->total_addOns->engine_secure_prem)) {

            $engine_seccure_amount = $premium_lists->total_addOns->engine_secure_prem;
        }

        if (!empty($premium_lists->total_addOns->tyre_secure_prem)) {

            $tyre_secure_amount = $premium_lists->total_addOns->tyre_secure_prem;
        }

        if (!empty($premium_lists->total_addOns->consumbale_expense_prem)) {

            $counsumable_amount = $premium_lists->total_addOns->consumbale_expense_prem;
        }

        if (!empty($premium_lists->total_addOns->repair_glass_prem)) {

            $repair_glass_prem = $premium_lists->total_addOns->repair_glass_prem;
        }

        if (!empty($premium_lists->total_addOns->rsa_prem)) {

            $rsa_amount = $premium_lists->total_addOns->rsa_prem;
        }

        if (!empty($premium_lists->total_addOns->emergency_expense_prem)) {

            $emergency_expense_amount = $premium_lists->total_addOns->emergency_expense_prem;
        }

        if (!empty($premium_lists->total_addOns->total_addon)) {

            $totalAddons = $premium_lists->total_addOns->total_addon;
        }


        if (!empty($premium_lists->total_tp_premium->cng_lpg_tp_prem)) {

            $lpg_cng_tp = $premium_lists->total_tp_premium->cng_lpg_tp_prem;
        }

        if (!empty($premium_lists->total_tp_premium->pa_unnamed_prem)) {

            $pa_unnamed     = $premium_lists->total_tp_premium->pa_unnamed_prem;
        }

        if (!empty($premium_lists->total_tp_premium->tppd_prem)) {

            $tppd_discount = $premium_lists->total_tp_premium->tppd_prem;
        }

        if (!empty($premium_lists->total_tp_premium->pa_paid_prem)) {

            $pa_paid  = $premium_lists->total_tp_premium->pa_paid_prem;
        }

        if (!empty($premium_lists->total_tp_premium->pa_unnamed_prem)) {

            $pa_unnamed  = $premium_lists->total_tp_premium->pa_unnamed_prem;
        }



        

        $policy_start_date = str_replace('/', '-', $data->data->pol_start_date);
        $policy_end_date = str_replace('/', '-', $data->data->pol_end_date);
        $previous_end_date =  Carbon::parse($data->data->pol_start_date)->subDay(1)->format('d-m-Y');

        $applicable_addons = [
            'zeroDepreciation',
            'roadSideAssistance',
            'lopb',
            'keyReplace',
            'consumables',
            'tyreSecure',
            'engineProtector',
            'returnToInvoice',
            'ncbProtection',
            'EmergTrnsprtAndHotelExpense'
        ];

        if ($is_liability) {
            $applicable_addons = [];
        }

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();
        $add_on_data = [
            'in_built'   => [],
            'additional' => [],
            'other'      => []
        ];


        switch ($masterProduct->product_identifier) {
            case 'SILVER':
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'key_replace'           => $key_replacment_amount,
                        'consumables'           => $counsumable_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'lopb'                  => $personal_belongings_amount,
                        'zero_depreciation'     => $zero_dep_amount,
                        'engine_protector'      => $engine_seccure_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => []
                ];

                $applicable_addons = [

                    'roadSideAssistance',
                    'lopb',
                    'keyReplace',
                    'consumables',
                    'tyreSecure',
                    'engineProtector',
                    'returnToInvoice',
                    'ncbProtection',
                ];
                break;

            case 'GOLD':
                $add_on_data = [
                    'in_built'   => [
                        'road_side_assistance'  => $rsa_amount,
                        'key_replace'           => $key_replacment_amount,
                        'lopb'                  => $personal_belongings_amount,
                    ],
                    'additional' => [
                        'consumables'           => $counsumable_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'zero_depreciation'     => $zero_dep_amount,
                        'engine_protector'      => $engine_seccure_amount,
                        'ncb_protection'        => $ncb_protect_amount,
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ],
                    'other'      => []
                ];
                break;

            case 'PEARL':
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'key_replace'           => $key_replacment_amount,
                        'lopb'                  => $personal_belongings_amount,
                    ],
                    'additional' => [
                        'engine_protector'      => $engine_seccure_amount,
                        'consumables'           => $counsumable_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => [
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ]
                ];
                break;

            case 'PEARL+':
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'key_replace'           => $key_replacment_amount,
                        'engine_protector'      => $engine_seccure_amount,
                        'consumables'           => $counsumable_amount,
                        'lopb'                  => $personal_belongings_amount,
                    ],
                    'additional' => [
                        'tyre_secure'           => $tyre_secure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => [
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ]
                ];
                break;

            case 'CORAL':
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'key_replace'           => $key_replacment_amount,
                        'consumables'           => $counsumable_amount,
                        'lopb'                  => $personal_belongings_amount,
                    ],
                    'additional' => [
                        'engine_protector'      => $engine_seccure_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => [
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ]
                ];
                break;

            case 'PLATINUM':
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'key_replace'           => $key_replacment_amount,
                        'engine_protector'      => $engine_seccure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'lopb'                  => $personal_belongings_amount,
                    ],
                    'additional' => [
                        'consumables'           => $counsumable_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => [
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ]
                ];
                break;

            case 'SAPPHIRE':
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'lopb'                  => $personal_belongings_amount,
                        'key_replace'           => $key_replacment_amount,
                        'consumables'           => $counsumable_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                    ],
                    'additional' => [
                        'engine_protector'      => $engine_seccure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => [
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ]
                ];
                break;

            case 'SAPPHIREPLUS':

                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'lopb'                  => $personal_belongings_amount,
                        'key_replace'           => $key_replacment_amount,
                        'consumables'           => $counsumable_amount,
                        'engine_protector'      => $engine_seccure_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                    ],
                    'additional' => [
                        'return_to_invoice'     => $return_to_invoice,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => [
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ]
                ];
                break;

            case 'SAPPHIRE++':

                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'lopb'                  => $personal_belongings_amount,
                        'key_replace'           => $key_replacment_amount,
                        'consumables'           => $counsumable_amount,
                        'engine_protector'      => $engine_seccure_amount,
                        'tyre_secure'           => $tyre_secure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ],
                    'additional' => [
                        'ncb_protection'        => $ncb_protect_amount,
                        ],
                    'other'      => []
                ];
                break;

            case 'PEARL++':
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'     => $zero_dep_amount,
                        'road_side_assistance'  => $rsa_amount,
                        'lopb'                  => $personal_belongings_amount,
                        'key_replace'           => $key_replacment_amount,
                        'consumables'           => $counsumable_amount,
                        'engine_protector'      => $engine_seccure_amount,
                        'return_to_invoice'     => $return_to_invoice,
                    ],
                    'additional' => [
                        'tyre_secure'           => $tyre_secure_amount,
                        'ncb_protection'        => $ncb_protect_amount,
                    ],
                    'other'      => [
                        'repair_of_glass,_fiber_and_plastic'  => $repair_glass_prem,
                        'emergency_medical_expenses'  => $emergency_expense_amount,
                    ]
                ];
                break;
        }


        $in_built_premium = 0;
        foreach ($add_on_data['in_built'] as $key => $value) {

            $in_built_premium = $in_built_premium + $value;
        }

        $additional_premium = 0;
        foreach ($add_on_data['additional'] as $key => $value) {
            $additional_premium = $additional_premium + $value;
        }

        $other_premium = 0;
        foreach ($add_on_data['other'] as $key => $value) {
            $other_premium = $other_premium + $value;
        }

        $add_on_data['in_built_premium'] = $in_built_premium;
        $add_on_data['additional_premium'] = $additional_premium;
        $add_on_data['other_premium'] = $other_premium;

        $rto_code = explode('-', $requestData->rto_code);

        $final_tp_premium = $Basic_TP_Premium + $pa_unnamed + $ll_paid_driver + $lpg_cng_tp + $pa_paid_driver +$tp_gio + $pa_paid   ;
        $final_total_discount = $ncb_discount + $other_discount + $voluntary_excess + $tppd_discount + $antitheft_discount ;
        $final_od_premium = $premiumType == 'third_party' ? 0 : $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $od_gio ;
        $final_net_premium   = round($final_od_premium +  $final_tp_premium - $final_total_discount  + $add_on_data['additional_premium'] + $add_on_data['in_built_premium']);
        $final_gst_amount   =  $premium_lists->net_premium;
        $final_payable_amount  = $premium_lists->final_premium;


        $data_response = [
            'status' => true,
            'msg' => 'Found',
            'product_identifier' => $masterProduct->product_identifier,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'Data' => [
                'idv' =>  $premiumType == 'third_party' ? 0 : (int) $idv,
                'min_idv' => $premiumType == 'third_party' ? 0 : (int) $min_idv,
                'max_idv' => $premiumType == 'third_party' ? 0 : (int) $max_idv,
                'vehicle_idv' => $premiumType == 'third_party' ? 0 : (int) $idv,
                'default_idv' => $is_liability ? 0 : $idv,
                'is_renewal' => 'Y',
                'pp_enddate' => $previous_end_date,
                'addonCover' => null,
                'addon_cover_data_get' => '',
                'rto_decline' => null,
                'rto_decline_number' => null,
                'mmv_decline' => null,
                'mmv_decline_name' => null,
                'policy_type' => $premiumType == 'comprehensive' ? 'Comprehensive' : ($is_liability ? 'Third Party' : 'Own Damage'),
                'business_type' => 'rollover',
                'cover_type' => '1YC',
                'vehicle_registration_no' => $requestData->rto_code,
                'rto_no' => $rto_code,
                'version_id' => $requestData->version_id,
                'selected_addon' => [],
                'showroom_price' => $premiumType == 'third_party' ? 0 : $data->data->vehicle_idv,
                'fuel_type' => $requestData->fuel_type,
                'ncb_discount' => $requestData->applicable_ncb,
                'company_name' => $productData->company_name,
                'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
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
                    'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_sub_type_name' => $productData->product_sub_type_name,
                    'flat_discount' => $productData->default_discount,
                    'predefine_series' => '',
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                ],
                'motor_manf_date' => $requestData->vehicle_register_date,
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'vehicleDiscountValues' => [
                    'master_policy_id' => $productData->policy_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'segment_id' => 0,
                    'rto_cluster_id' => 0,
                    'car_age' => 2, //$bike_age,
                    'aai_discount' => 0,
                    'ic_vehicle_discount' => round(abs($other_discount)),
                ],
                'ic_vehicle_discount' => round(abs($other_discount)),
                'basic_premium' => $premiumType == 'third_party' ? 0 : round($basic_od),
                'motor_electric_accessories_value' => $premiumType == 'third_party' ? 0 : round($electrical_accessories),
                'motor_non_electric_accessories_value' => $premiumType == 'third_party' ? 0 :  round($non_electrical_accessories),
                'motor_lpg_cng_kit_value' => $premiumType == 'third_party' ? 0 : round($lpg_cng),
                'total_accessories_amount(net_od_premium)' => $premiumType == 'third_party' ? 0 :  round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                'total_own_damage' => $premiumType == 'third_party' ? 0 : round($final_od_premium),
                'tppd_premium_amount' =>  $Basic_TP_Premium,
                'tppd_discount' => $tppd_discount,
                'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                'cover_unnamed_passenger_value' => $pa_unnamed,
                'default_paid_driver' => $ll_paid_driver,
                'motor_additional_paid_driver' => $pa_paid,
                // 'included_additional' => $included_additional = ['included' => []],
                'cng_lpg_tp' => round($lpg_cng_tp),
                'seating_capacity' => 2,
                'deduction_of_ncb' => $premiumType == 'third_party' ? 0 : round(abs($ncb_discount)),
                'antitheft_discount' => $antitheft_discount,
                'aai_discount' => '', //$automobile_association,
                'voluntary_excess' => $voluntary_excess,
                'other_discount' => round(abs($other_discount)),
                'total_liability_premium' => round($final_tp_premium),
                'net_premium' => round($final_net_premium),
                'service_tax_amount' => round($final_gst_amount),
                'service_tax' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'quotation_no' => '',
                'premium_amount' => ($final_payable_amount),

                'service_data_responseerr_msg' => 'success',
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'service_err_code' => null,
                'service_err_msg' => null,
                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                'ic_of' => $productData->company_id,
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'get_policy_expiry_date' => null,
                'get_changed_discount_quoteid' => 0,
                'vehicle_discount_detail' => [
                    'discount_id' => null,
                    'discount_rate' => null,
                ],
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online,
                'policy_id' => $productData->policy_id,
                'insurane_company_id' => $productData->company_id,
                'max_addons_selection' => null,

                'add_ons_data' =>  $add_on_data,
                'applicable_addons' => $applicable_addons,
                'GeogExtension_ODPremium'                     => $od_gio,
                'GeogExtension_TPPremium'                     => $tp_gio,
                'final_od_premium'  => $premiumType == 'third_party' ? 0 :  round($final_od_premium),
                'final_tp_premium'  => round($final_tp_premium),
                'final_total_discount' => $final_total_discount,
                'final_net_premium' => round($final_net_premium),
                'final_gst_amount'  => round($final_gst_amount),
                'final_payable_amount' => $final_payable_amount,
                'mmv_detail'    => $mmv_data,
            ],
        ];

        $included_additional = [
            'included' => []
        ];
      

        if($pa_paid){
            $included_additional['included'][] = 'motor_additional_paid_driver';
        }
        if($electrical_accessories){
            $included_additional['included'][] = 'motorElectricAccessoriesValue';
        }
        if($non_electrical_accessories){
            $included_additional['included'][] = 'motorNonElectricAccessoriesValue';
        }

        if($lpg_cng || in_array($mmv->txt_fuel, ['CNG', 'PETROL+CNG', 'DIESEL+CNG', 'LPG'])){
            $included_additional['included'][] = 'motorLpgCngKitValue';
        }
        $data_response['Data']['included_additional'] = $included_additional;
        
        return camelCase($data_response);
    } else {
        return
            [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => $response_data->message_txt ??'Insurer not reachable'
            ];
    }
}