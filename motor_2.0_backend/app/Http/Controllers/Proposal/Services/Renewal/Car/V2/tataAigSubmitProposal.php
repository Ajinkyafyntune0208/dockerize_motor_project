<?php

namespace App\Http\Controllers\Proposal\Services\Renewal\Car\V2;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\ckycUploadDocuments;
use App\Models\ShriramPinCityState;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\SyncPremiumDetail\Car\TataAigPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\Storage;
use App\Models\MasterProduct;



include_once app_path() . '/Helpers/CarWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/TataAigCkycHelper.php';


class tataAigSubmitProposal
{

    public static function renewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'tata_aig');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];

        $premiumType =  MasterPremiumType:: 
            where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();


        $is_package     = (($premiumType == 'comprehensive' || $premiumType == 'breakin') ? true : false);
        $is_liability   = (($premiumType == 'third_party' || $premiumType == 'third_party_breakin') ? true : false);
        $is_od          = (($premiumType == 'own_damage' || $premiumType == 'own_damage_breakin') ? true : false);
        $is_indivisual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
        $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);

        $vehicleDetails = [

            'manufacture_name' => $mmv->manufacturer,
            'model_name' => $mmv->vehiclemodel,
            'version' => $mmv->txt_varient,
            'fuel_type' => $mmv->txt_fuel,
            'seating_capacity' => $mmv->seatingcapacity,
            'carrying_capacity' => $mmv->seatingcapacity - 1,
            'cubic_capacity' => $mmv->cubiccapacity,
            'gross_vehicle_weight' => $mmv->grossvehicleweight,
            'vehicle_type' => 'PRIVATE CAR'
        ];


        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Token Generation',
            'transaction_type'  => 'proposal',
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

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);


        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $compulsory_personal_accident = ($selected_addons->compulsory_personal_accident == null ? [] : $selected_addons->compulsory_personal_accident);


        $pa_owner  = $ll_paid_driver = $is_tppd =  $is_Geographical =   $PAforaddionaldPaidDriver =  $is_pa_paid = 0;
        $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI =  $ElectricalaccessSI = $NonElectricalaccessSI = '';
        $PAforUnnamedPassenger  =  $Electricalaccess = $NonElectricalaccess = $externalCNGKIT = $externalCNGKITSI =  $is_anti_theft =  "false";

        $voluntary_excess_amt = '';


        $is_electrical = false;
        $is_non_electrical = false;
        $is_lpg_cng = false;

        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value) && !$is_liability) {
                $Electricalaccess = "Yes";
                $ElectricalaccessSI = $value['sumInsured'];
                $is_electrical = true;
            }

            elseif (in_array('Non-Electrical Accessories', $value) && !$is_liability) {
                $NonElectricalaccess = "Yes";
                $NonElectricalaccessSI = $value['sumInsured'];
                $is_non_electrical = true;
            }

            elseif (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = "Yes";
                $externalCNGKITSI = $value['sumInsured'];
                $is_lpg_cng = true;
                if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                    $mmv->txt_fuel = 'External CNG';
                    $mmv->txt_fuelcode = '5';
                }
            }

            elseif (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
                $PAPaidDriverConductorCleaner = "Yes";
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }

        foreach ($discounts as $key => $discount) {
            if ($discount['name'] == 'anti-theft device' && !$is_liability) {
                $is_anti_theft = true;
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            elseif ($discount['name'] == 'voluntary_insurer_discounts' && isset($discount['sumInsured']) && !$is_liability) {
                $is_voluntary_access = true;
                $voluntary_excess_amt = $discount['sumInsured'];
            }

            elseif ($discount['name'] == 'TPPD Cover' && !$is_od) {
                $is_tppd = true;
                $tppd_amt = '9999';
            }
        }


        $igst                   = $anti_theft = $other_discount = $tp_gio = $od_gio  =
            $rsapremium             = $pa_paid_driver = $zero_dep_amount =
            $ncb_discount           = $tppd = $final_tp_premium = $antitheft_discount =
            $final_od_premium       = $final_net_premium =
            $final_payable_amount   = $basic_od = $electrical_accessories =
            $lpg_cng_tp             = $lpg_cng = $non_electrical_accessories = $tppd_discount =
            $pa_owner               = $voluntary_excess = $pa_unnamed =
            $engine_protection = $consumables_cover = $return_to_invoice = $pa_paid  =  $pa_unnamed = 0;
        $final_gst_amount       = $vehicle_in_90_days =  $Basic_TP_Premium = $return_to_invoice = $zero_dep_amount = $ncb_protect_amount = $key_replacment_amount = $personal_belongings_amount = $engine_seccure_amount = $tyre_secure_amount = $counsumable_amount =  $repair_glass_prem = $rsa_amount = $emergency_expense_amount =  $totalAddons = 0;
        $countries = [];

        foreach ($additional_covers  as $key => $value) {

            if (in_array('LL paid driver', $value)) {

                $llpaidDriverSI = $value['sumInsured'];
                $ll_paid_driver = true;
            }


            elseif (in_array('Unnamed Passenger PA Cover', $value)) {
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
                $countries = $value['countries'];
                $is_Geographical = "true";
            }
        }


        foreach ($compulsory_personal_accident  as $key => $value) {
            if (in_array('Compulsory Personal Accident', $value)) {
                $pa_owner = true;
            }
        }

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $policynumber = str_replace('-00', '',$user_proposal->previous_policy_number);
        $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
        ->first();
        $idv        =  $quote_log_data->idv;      

        $vehicle_register_no = explode("-", $proposal->vehicale_registration_number);

        $requestdata = [
            "__finalize" => "1",
            "PolicyNumber" => $policynumber,
            "registration_no" => "",
            "pa_owner" =>   $pa_owner == true && ($is_individual && !$is_od && !$is_liability) ? "Yes" : "",
            'pa_owner_declaration'          => 'None',
            'pa_owner_tenure'               =>  $pa_owner == true && ($is_individual && !$is_od && !$is_liability) ? '1' : "",
            'vehicle_idv' => $idv,
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
            "pa_paid_no" =>  "1" ,
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

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Calculation Renewal',
            'transaction_type'  => 'proposal',
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

        $data_quote = $get_response['response'];

        $data_quote = $requestdata;
        $fetch_url = config('IC.TATA_AIG.V2.CAR.END_POINT_URL_RENEWAL_QUOTE'); //https://uatapigw.tataaig.com/pc-motor-renewal/v2/quote

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Re-Calculation Renewal',
            'transaction_type'  => 'proposal',
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

        $data_quote = $get_response['response'];    

        if ($data_quote->status != '200') {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $data_quote->message_txt ?? 'Insurer Not Reachable.'
            ]);
        }  
        
        if ($is_individual) {
            if ($proposal->gender == "M" || $proposal->gender == "Male") {
                $gender = 'Male';
                $insured_prefix = 'Mr';
            } else {
                $gender = 'Female';
                if ($proposal->marital_status != "Single") {
                    $insured_prefix = 'Mrs';
                } else {
                    $insured_prefix = 'Ms';
                }
            }
        } else {
            $gender = 'Others';
            $insured_prefix = 'M/s.';
        }

        $webserviceId = $get_response['webservice_id'];

        $proposal_additional_details = $proposal->additional_details;
        
        $pucExpiry = $pucNo = '';

        if (!is_array($proposal_additional_details) && !empty($proposal_additional_details)) {
            $proposal_additional_details = json_decode($proposal_additional_details, true);
        }
        if(isset($proposal_additional_details['vehicle']['pucExpiry']))
        {
            $pucExpiry = Carbon::parse($proposal_additional_details['vehicle']['pucExpiry'])->format('Y-m-d');
        }
        if(isset($proposal_additional_details['vehicle']['pucNo']))
        {
            $pucNo = $proposal_additional_details['vehicle']['pucNo'];
        }
       
        $inputArray = [
            "quote_id" => $data_quote->data->quote_id,
            "proposal_id" => $data_quote->data->proposal_id,
            "proposer_mobile" =>  $proposal->mobile_number,
            "proposer_salutation" => $insured_prefix,
            "proposer_marital" => $is_individual ? $proposal->marital_status : '',
            "proposer_dob" =>  $is_individual ? Carbon::parse($proposal->dob)->format('Y-m-d') : '' ,
            "proposer_gender" => $gender,
            "proposer_occupation" => 'SERVICE',
            'nominee_age' => '20',
            'nominee_name' => (($is_individual && !$is_od) ? ($proposal->nominee_name ?? 'NA') : ''),
            'nominee_relation' =>"Brother",
            "ble_tp_name" => "",
            "__finalize" => "1",
            'vehicle_puc_expiry' => $pucExpiry,
            'vehicle_puc' => $pucNo,
            'vehicle_puc_declaration' => true,
        ];   
        $fetch_url = config('IC.TATA_AIG.V2.CAR.END_POINT_URL_RENEWAL_PROPOSAL'); //https://uatapigw.tataaig.com/pc-motor-renewal/v1/proposal

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Proposal Submit',
            'transaction_type'  => 'proposal',
            'type'              => 'renewal',
            'headers' => [
                'Content-Type'   => "application/json",
                'Connection'     => "Keep-Alive",
                'Authorization'  =>  $tokenResponse,
                'Accept'         => "application/json",
                'x-api-key'      =>   config("IC.TATA_AIG.V2.CAR.FETCH_KEY_ID_RENEWAL"),


            ]
        ];


        $get_response = getWsData($fetch_url, $inputArray, 'tata_aig_v2', $additional_data);
        $get_response['response'] = json_decode($get_response['response']);
        $data_proposal = $get_response['response'];

        $response = $data_proposal;

        if (empty($response) || $response->status !='200' ) {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => $response->message_txt ?? 'Insurer Not Reachable.'
            ]);
        }       
        if ($response->message_txt == "Proposal submitted successfully") {


            $premium_lists = $data_quote->data->premiumSchemes[0]->premium_break_up;


            $igst                   = $anti_theft = $other_discount = $tp_gio = $od_gio  =
                $rsapremium             = $pa_paid_driver = $zero_dep_amount =
                $ncb_discount           = $tppd = $final_tp_premium = $antitheft_discount =
                $final_od_premium       = $final_net_premium =
                $final_payable_amount   = $basic_od = $electrical_accessories =
                $lpg_cng_tp             = $lpg_cng = $non_electrical_accessories = $tppd_discount =
                $pa_owner               = $voluntary_excess = $pa_unnamed =
                $engine_protection = $consumables_cover = $return_to_invoice = $pa_paid  =  $pa_unnamed = 0;
            $final_gst_amount       = $vehicle_in_90_days =  $Basic_TP_Premium = $return_to_invoice = $zero_dep_amount = $ncb_protect_amount = $key_replacment_amount = $personal_belongings_amount = $engine_seccure_amount = $tyre_secure_amount = $counsumable_amount =  $repair_glass_prem = $rsa_amount = $emergency_expense_amount =  $totalAddons =  $final_addon_amount = $OD_Total =  0;


            if ($premiumType != 'third_party' && !empty($premium_lists->total_od_premium->od->basic_od)) {

                $basic_od = $premium_lists->total_od_premium->od->basic_od;
            }

            if (!empty($premium_lists->net_premium)) {
                $final_net_premium = $premium_lists->net_premium;
            }

            if ($premiumType != 'third_party' && !empty($premium_lists->total_od_premium->discount_od->ncb_prem)) {

                $ncb_discount = $premium_lists->total_od_premium->discount_od->ncb_prem;
            }

            if ($premiumType != 'third_party' && !empty($premium_lists->total_od_premium->total_od)) {
                $OD_Total = $premium_lists->total_od_premium->total_od;
            }

            if (!empty($premium_lists->total_tp_premium->basic_tp)) {
                $Basic_TP_Premium = $premium_lists->total_tp_premium->basic_tp;
            }

            if (!empty($premium_lists->total_tp_premium->pa_owner_prem) && $pa_owner !== 0) {

                if (isset($compulsory_personal_accident) && is_array($compulsory_personal_accident)) {
                    foreach ($compulsory_personal_accident as $key => $value) {
                        if (in_array('Compulsory Personal Accident', $value)) {
                            $pa_owner = true;
                        }
                    }

                    if ($pa_owner === true) {
                        $pa_owner = $premium_lists->total_tp_premium->pa_owner_prem;
                    }
                }
            }

            if (!empty($premium_lists->total_tp_premium->ll_paid_prem)) {
                $Legal_Liability_To_Employees = $premium_lists->total_tp_premium->ll_paid_prem;
            }

            if (!empty($premium_lists->total_tp_premium->total_tp)) {
                $final_tp_premium = $premium_lists->total_tp_premium->total_tp;
            }


            if (!empty($premium_lists->final_premium)) {
                $final_payable_amount = $premium_lists->final_premium;
            }

            if ($premiumType != 'third_party' && !empty($premium_lists->total_od_premium->od->electrical_prem)) {
                $electrical_accessories = $premium_lists->total_od_premium->od->electrical_prem;
            }

            if ($premiumType != 'third_party' && !empty($premium_lists->total_od_premium->od->non_electrical_prem)) {
                $non_electrical_accessories = $premium_lists->total_od_premium->od->non_electrical_prem;
            }

            if ($premiumType != 'third_party' && !empty($premium_lists->total_od_premium->od->cng_lpg_od_prem)) {
                $lpg_cng = $premium_lists->total_od_premium->od->cng_lpg_od_prem;
            }

            if (!empty($premium_lists->total_tp_premium->ll_paid_prem) && $ll_paid_driver !== 0) {

                $ll_paid_driver = $premium_lists->total_tp_premium->ll_paid_prem;
            }

            if (!empty($premium_lists->total_addOns->total_addon)) {

                $final_addon_amount = $premium_lists->total_addOns->total_addon;
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

            if (!empty($premium_lists->total_od_premium->od->geography_extension_od_prem)) {

                $od_gio  = $premium_lists->total_od_premium->od->geography_extension_od_prem;
            }

            if (!empty($premium_lists->total_tp_premium->geography_extension_tp_prem)) {

                $tp_gio  = $premium_lists->total_tp_premium->geography_extension_tp_prem;
            }


            if (!empty($premium_lists->total_od_premium->discount_od->atd_disc_prem)) {

                $antitheft_discount = $premium_lists->total_od_premium->discount_od->atd_disc_prem;
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



            $final_tp_premium = $Basic_TP_Premium + $pa_unnamed + $ll_paid_driver + $lpg_cng_tp + $pa_paid_driver + $tp_gio + $pa_paid ;

            $applicable_addons = [
                'zeroDepreciation',
                'roadSideAssistance',
                'lopb',
                'keyReplace',
                'consumables',
                'tyreSecure',
                'engineProtector',
                'returnToInvoice',
                'ncbProtection'
            ];

            if ($premiumType == 'third_party' || $premiumType == 'third_party_breakin') {
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
            // echo "<pre>";print_r([$add_on_data, $totalAddons]);echo "</pre>";die();

            $in_built_premium = 0;
            foreach ($add_on_data['in_built'] as $key => $value) {
                if ($value === 0) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'No value for In-Built addon'
                    ];
                }
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


            $final_total_discount = $ncb_discount + $other_discount + $voluntary_excess + $tppd_discount + $antitheft_discount ;
            $final_od_premium = $premiumType == 'third_party' ? 0 : $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $od_gio;




            $policy_start_date = Carbon::parse($data_quote->data->pol_start_date)->format('d-m-Y');
            $policy_end_date = Carbon::parse($data_quote->data->pol_end_date)->format('d-m-Y');

            $proposal->idv          = $premiumType == 'third_party' ? 0 : $idv;
            $proposal->proposal_no          = $response->data[0]->proposal_no;
            $proposal->od_premium           = $OD_Total;
            $proposal->tp_premium           = $final_tp_premium;
            $proposal->ncb_discount         = abs($ncb_discount);
            $proposal->addon_premium        = $final_addon_amount; 
            $proposal->total_premium        =   $final_net_premium;
            $proposal->service_tax_amount   =  ($final_payable_amount * 0.18);
            $proposal->final_payable_amount =  $final_payable_amount;
            $proposal->cpa_premium          = $pa_owner;
            $proposal->total_discount       = abs($ncb_discount) + abs($other_discount) + $tppd_discount;
            $proposal->ic_vehicle_details   = $vehicleDetails;
            $proposal->policy_start_date    = $policy_start_date;
            $proposal->policy_end_date      = $policy_end_date;


            $tata_aig_v2_data = [
                'quote_no'       => $response->data[0]->quote_no,
                'proposal_no'    => $response->data[0]->proposal_no,
                'proposal_id'    => $response->data[0]->proposal_id,
                'payment_id'     => $response->data[0]->payment_id,
                'document_id'    => $response->data[0]->document_id,
                'policy_id'      => $response->data[0]->policy_id,
                'master_policy_id' => $productData->policy_id,
            ];

            $proposal_additional_details['tata_aig_v2'] = $tata_aig_v2_data;
            $proposal->additional_details = $proposal_additional_details;
            $proposal->additional_details_data = $proposal_additional_details;
            $proposal->save();
            $data['user_product_journey_id'] = $enquiryId;
            $data['ic_id'] = '24';
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);

            TataAigPremiumDetailController::saveV2PremiumDetails($webserviceId);

            $is_breakin_case =  'N';


            if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_ENABLED_TATA_AIG') == 'Y') {

                try {
                    $is_breakin_case =  'N';
                    // $validateCKYC = self::validateCKYC($proposal, $proposalResponse, $get_response, $is_breakin_case);

                    if (config('constants.IcConstants.tata_aig_v2.IS_NEW_CKYC_FLOW_ENABLED_FOR_TATA_AIG_V2') == 'Y') {
                        $webserviceData = $get_response;
                        // $proposalSubmitResponse = $proposalResponse; //previous
                        $proposalSubmitResponse = $proposal;
                        $validateCKYC = ckycVerifications(compact('proposal', 'proposalSubmitResponse', 'webserviceData', 'is_breakin_case'));

                        $validateCKYCJSON = $validateCKYC;
                        if (! $validateCKYC['status']) {

                            UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                                ->update([
                                    'is_breakin_case' => 'N',
                                ]);

                            return response()->json($validateCKYC);
                        }
                    } else {
                        $validateCKYC = self::validateCKYC($proposal, $proposalResponse, $get_response, $is_breakin_case);

                        $validateCKYCJSON = $validateCKYC->getOriginalContent();

                        if (!$validateCKYCJSON['status']) {
                            return $validateCKYC;
                        }
                    }
                    return $validateCKYC;
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => false,
                        'message' => $e->getMessage(),
                        'dev_msg' => 'Line No. : ' . $e->getLine(),
                    ]);
                }
            }
            $submitProposalResponse = [
                'status' => true,
                'msg' => "Proposal Submitted Successfully!",
                'data' => ([
                    'proposal_no'        => $response->data[0]->proposal_no,
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'kyc_status' => true

                ]),
            ];
            if(config('constants.IS_CKYC_ENABLED_TATA_AIG') != 'Y') {
                $submitProposalResponse['data']['verification_status'] = true;
            }

            return response()->json($submitProposalResponse);
        }
        return response()->json([
            'status' => false,
            'msg' => 'Something went wrong.'
        ]);
    }

    public static function validateCKYC(UserProposal $proposalData, array $proposalSubmitResponse, array $webserviceData, $is_breakin_case)
    {
        $request_data = [
            "companyAlias" => "tata_aig",
            "enquiryId" => customEncrypt($proposalData->user_product_journey_id),
            "mode" => 'pan_number',
        ];
        $ckycController = new CkycController;
        $ckyc_response = $ckycController->ckycVerifications(new Request($request_data));
        $ckyc_response = $ckyc_response->getOriginalContent();
        if ($ckyc_response['data']['verification_status'] == true) {
            return response()->json([
                'status' => true,
                'ckyc_status' => true,
                'msg' => 'Proposal Submited Successfully..!',
                'webservice_id' => $webserviceData['webservice_id'],
                'table' => $webserviceData['table'],
                'data' => [
                    'verification_status' => true,
                    'proposalId' => $proposalData->user_proposal_id,
                    'userProductJourneyId' => $proposalData->user_product_journey_id,
                    'proposalNo' => $proposalSubmitResponse['proposal_no'],
                    'finalPayableAmount' => $proposalData->final_payable_amount,
                    'is_breakin' => $is_breakin_case,
                    'isBreakinCase' => $is_breakin_case,
                    'inspection_number' => (isset($proposalSubmitResponse['ticket_number']) ? $proposalSubmitResponse['ticket_number'] : ''),
                    'kyc_verified_using' => $ckyc_response['ckyc_verified_using'],
                    'kyc_status' => true
                ],
            ]);
        } else {
            if (!empty($ckyc_response['data']['otp_id'] ?? '')) {
                return response()->json([
                    "status" => true,
                    "message" => "OTP Sent Successfully!",
                    "data" => [
                        "verification_status" => false,
                        "message" => "OTP Sent Successfully!",
                        'otp_id' => $ckyc_response['data']['otp_id'],
                        'is_breakin' => 'N',
                        'isBreakinCase' => 'N',
                        'kyc_status' => false
                    ]
                ]);
            }
            return response()->json([
                'status' => false,
                'ckyc_status' => false,
                'msg' => $ckyc_response['data']['message'] ?? 'Something went wrong while doing the CKYC. Please try again.',
            ]);
        }
    }
}
