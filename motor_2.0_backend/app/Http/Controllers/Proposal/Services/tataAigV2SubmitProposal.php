<?php

namespace App\Http\Controllers\Proposal\Services;

use Config;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Str;
use TataAigV2Helper;

use App\Models\QuoteLog;
use App\Models\UserProposal;
use App\Models\ckycUploadDocuments;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\CvBreakinStatus;
use App\Models\PreviousInsurerList;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Services\TataAigPremiumDetailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\TataAigV2SpecialRegNo;

include_once app_path() . "/Helpers/CvWebServiceHelper.php";
include_once app_path() . '/Helpers/CkycHelpers/TataAigCkycHelper.php';
include_once app_path() . '/Helpers/IcHelpers/TataAigV2Helper.php';

class tataAigV2SubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        // dd($proposal);

        $configCreds = self::getTataAigV2CvCreds();

        $enquiryId      = customDecrypt($request["userProductJourneyId"]);
        $requestData    = getQuotation($enquiryId);
        // dd($requestData);
        $productData    = getProductDataByIc($request["policyId"]);

        $masterProduct  = MasterProduct::where("master_policy_id", $productData->policy_id)
            ->first();
        $premium_type   = DB::table("master_premium_type")
            ->where("id", $productData->premium_type_id)
            ->pluck("premium_type_code")
            ->first();

        $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
            ->first();

        $additionalDetailsData = json_decode($proposal->additional_details); 

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $breakin = in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin']);

        $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
        $is_individual  = $requestData->vehicle_owner_type == "I" ? true : false;
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_breakin     = ($requestData->business_type == "breakin");

        $noPrevPolicy   = ($requestData->previous_policy_type == 'Not sure');

        $idv            = $quote_log_data->idv;

        $check_mmv      = self::checkTataAigMMV($productData, $requestData->version_id, $requestData);
        $partially_build = $check_mmv['is_partial_build'];

        if (!$check_mmv['status']) {
            return $check_mmv;
        }

        $mmv            = (object)$check_mmv['data'];

        if (config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_MMV_CHANGE_TO_UAT') == 'Y') {
            $mmv = self::changeToUAT($mmv);
        }
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        // dd($mmv);
       
        $customer_type  = $is_individual ? "Individual" : "Organization";
        $vehicle_age = 0;
        if ($is_new) {
            $policyStartDate  = strtotime($requestData->vehicle_register_date); //date('Y-m-d');

            if ($is_liability) {
                $policyStartDate  = strtotime($requestData->vehicle_register_date);
            }

            $policy_start_date = date('Y-m-d', $policyStartDate);

            $policy_end_date = date('Y-m-d', strtotime($policy_start_date . '-1 days + 1 year'));
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = $policy_end_date;
        } else {
            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == "New" ? date("Y-m-d") : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = $interval->y * 12 + $interval->m + 1;
            $vehicle_age = $interval->y;

            $motor_manf_date = "01-" . $requestData->manufacture_year;

            $current_date = date("Y-m-d");

            if ($is_breakin) {
                $policy_start_date = date("Y-m-d", strtotime(date('Y-m-d') . "+1 days"));
            } else {
                $policy_start_date = date("Y-m-d", strtotime($requestData->previous_policy_expiry_date . " + 1 days"));
            }

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date("Y-m-d", strtotime(date('Y-m-d') . "+1 days"));
            }

            $policy_end_date = date("Y-m-d", strtotime($policy_start_date . " - 1 days + 1 year"));
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = $policy_end_date;
        }

        // $policy_start_date  = date('Ymd', strtotime($policy_start_date));
        // $policy_end_date    = date('Ymd', strtotime($policy_end_date));

        $vehicle_register_no = explode("-", $proposal->vehicale_registration_number);

        $previousInsurerList = PreviousInsurerList::where([
            "company_alias" => 'tata_aig_v2',
            "name" => $proposal->insurance_company_name,
        ])->first();

        // dd($previousInsurerList);


        // addon
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

        $is_imt_23 = "No";

        if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '')
        {
            
            foreach ($selected_addons->applicable_addons as $applicable_addon)
            {
                if ($applicable_addon['name'] == 'IMT - 23')
                {
                    $is_imt_23 = "Yes";
                }
            }
        }
        
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAPaidDriverConductorCleaner = $PAforaddionaldPassenger = $llpaidDriver = $NonElectricalaccess = $is_Geographical = "No";

        $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = '';

        $is_anti_theft = false;
        $is_automobile_assoc = false;
        $is_anti_theft_device_certified_by_arai = "false";
        $is_tppd = false;
        $is_voluntary_access = 'No';
        $voluntary_excess_amt = '';

        $is_electrical = false;
        $is_non_electrical = false;
        $is_lpg_cng = false;

        $is_ll_paid = "No";
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $is_electrical = true;
                $Electricalaccess = "Yes";
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $is_non_electrical = true;
                $NonElectricalaccess = "Yes";
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $is_lpg_cng = true;
                $externalCNGKIT = "Yes";
                $externalCNGKITSI = $value['sumInsured'];
                if ($mmv->txt_fuel_type != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                    // $mmv->txt_fuel = 'External CNG';
                    // $mmv->txt_fuelcode = '5';
                }
            }

            if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
                $PAPaidDriverConductorCleaner = "Yes";
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }
        $LLNumberDriver = 0;
        $LLNumberConductor = 0;
        $LLNumberCleaner = 0;
        $countries = [];

        foreach ($additional_covers as $key => $value) {
            if (in_array('PA paid driver/conductor/cleaner', $value)) {
                $PAforaddionaldPaidDriver = "Yes";
                $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            }

            if ($value['name'] == 'LL paid driver/conductor/cleaner') {
                $LLNumberDriver = $value['LLNumberDriver'] ?? 0;
                $is_ll_paid = "Yes";
                $LLNumberCleaner = $value['LLNumberCleaner'] ?? 0;
                $is_ll_paid = "Yes";
                $LLNumberConductor = $value['LLNumberConductor'] ?? 0;
                $is_ll_paid = "Yes";

                $llpaidDriver = (in_array('DriverLL', $value['selectedLLpaidItmes']) || in_array('CleanerLL', $value['selectedLLpaidItmes']) || in_array('ConductorLL', $value['selectedLLpaidItmes'])) ? 'Yes' : 'No';
            }
            if ($value['name'] == 'Geographical Extension') {
                $countries = $value['countries'] ;
                $is_Geographical = "Yes";
            }

        }

        foreach ($discounts as $key => $discount) {
            if ($discount['name'] == 'anti-theft device' && !$is_liability) {
                $is_anti_theft = true;
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            if ($discount['name'] == 'voluntary_insurer_discounts' && isset($discount['sumInsured'])) {
                $is_voluntary_access = "Yes";
                $voluntary_excess_amt = $discount['sumInsured'];
            }

            if ($discount['name'] == 'TPPD Cover' && !$is_od) {
                $is_tppd = true;
            }
        }

        if (config('constants.IcConstants.tata_aig_v2.NO_VOLUNTARY_DISCOUNT') == 'Y') {
            $is_voluntary_access = false;
            $voluntary_excess_amt = '';
        }

        // cpa vehicle

        $proposal_additional_details = json_decode($proposal->additional_details, true);
        // dd($proposal_additional_details);

        // dd($proposal_additional_details);


        // cpa vehicle
        $driver_declaration = "None";
        $pa_owner_tenure = '';
        if (isset($selected_addons->compulsory_personal_accident[0]["name"])) {
            $cpa_cover = true;
            $driver_declaration = "None";

            $tenure = 1;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : $tenure;
            if ($tenure === 3 || $tenure === '3') {
                $pa_owner_tenure = '3';
            } else {
                $pa_owner_tenure = '1';
            }
        } else {

            $cpa_cover = false;
            if ($customer_type == "Individual") {
                if (isset($proposal_additional_details["prepolicy"]["reason"]) && $proposal_additional_details["prepolicy"]["reason"] == "I have another motor policy with PA owner driver cover in my name") {

                    $driver_declaration = "Other motor policy with CPA";
                } elseif (isset($proposal_additional_details["prepolicy"]["reason"]) && in_array($proposal_additional_details["prepolicy"]["reason"], ["I have another PA policy with cover amount greater than INR 15 Lacs", "I have another PA policy with cover amount of INR 15 Lacs or more"])) {
                    $driver_declaration = "Insured has standalone PA cover >= 15 lakhs";
                } elseif (isset($proposal_additional_details["prepolicy"]["reason"]) && $proposal_additional_details["prepolicy"]["reason"] == "I do not have a valid driving license.") {
                    $driver_declaration = "Owner driver does not hold valid Driving license";
                } else {
                    $driver_declaration = "None";
                }
            }
        }

        // ADDONS
        $applicableAddon = self::getApplicableAddons($masterProduct, $is_liability,);
        // $applicableAddon = getApplicableAddonstata($masterProduct, $is_liability, $interval);
        // if($is_zero_dep && $vehicle_age >= 5){
        // return [
        //     'status' => false,
        //     'message' => 'Zero Dept is not available above 5 Year Vehicle',
        // ];
    // }   

        $applicableAddon['RoadsideAssistance'] = "No";
        $applicableAddon['NCBProtectionCover'] = "No";
        $applicableAddon['DepreciationReimbursement'] = "No";
        foreach ($addons as $key => $value) {
            if (!$is_liability && in_array('Road Side Assistance', $value)) {
                $applicableAddon['RoadsideAssistance'] = "Yes";
            }

            if (!$is_liability && in_array('NCB Protection', $value)) {
                $applicableAddon['NCBProtectionCover'] = "Yes";
            }

            if ($value['name'] == 'Zero Depreciation' && ($is_new || $interval->y < 5) && $productData->zero_dep == '0') {
                $applicableAddon['DepreciationReimbursement'] = "Yes";
            }

            if (!$is_liability && in_array('IMT - 23', $value)) {
                $is_imt_23 = "Yes";
            }
        }

        if ($is_new || $requestData->applicable_ncb < 25) { //NCB protection cover is not allowed for NCB less than or equal to 20%
            $applicableAddon['NCBProtectionCover'] = "No";
        }
        // END ADDONS

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_aadhar = '';
        $pos_pan    = '';
        $sol_id     = ""; //config('constants.IcConstants.tata_aig.SOAL_ID');
        $is_posp = 'N';
        $q_office_location = 0;

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type', 'P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log_data->idv <= 5000000) {
            if (!empty($pos_data->pan_no)) {
                $is_posp = 'Y';
                $sol_id = $pos_data->pan_no;
                $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
            }
        } elseif (config('constants.motor.constants.IcConstants.tata_aig_v2.cv.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2') == 'Y') {
            $is_posp = 'Y';
            $sol_id     = '55554';//'840372';
            $q_office_location = 90431;//90200;
        } else {
            $is_pos = 'N';
        }

        if ($is_od) {
            $tp_insured         = $proposal_additional_details['prepolicy']['tpInsuranceCompany'];
            $tp_insurer_name    = $proposal_additional_details['prepolicy']['tpInsuranceCompanyName'];
            $tp_start_date      = $proposal_additional_details['prepolicy']['tpStartDate'];
            $tp_end_date        = $proposal_additional_details['prepolicy']['tpEndDate'];
            $tp_policy_no       = $proposal_additional_details['prepolicy']['tpInsuranceNumber'];

            $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
            $tp_insurer_address = keysToLower($tp_insurer_address);
        }

        $rto_code = explode('-', $requestData->rto_code);

        $rto_data = DB::table('tata_aig_v2_rto_master')
            //->where('txt_rto_code', 'like', '%' . $rto_code[0] . $rto_code[1] . '%')
            ->where('txt_rto_code', str_replace('-', '',RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
            ->first();

        $token_response = self::getToken($enquiryId, $productData);

        if (!$token_response['status']) {

            return $token_response;
        }

        if (config('constants.IcConstants.tata_aig_v2.NO_ANTITHEFT') == 'Y') {
            $is_anti_theft = false;
        }

        if (config('constants.IcConstants.tata_aig_v2.NO_NCB_PROTECTION') == 'Y') {
            $applicableAddon['NCBProtectionCover'] = 'No';
        }
        // dd($mmv->txt_segment);
        if (in_array(strtoupper($mmv->txt_segment), ['MINI', 'COMPACT', 'MPS SUV', 'MPV SUV', 'MID SIZE'])) {
            $engineProtectOption = 'WITH DEDUCTIBLE';
        } else {
            $engineProtectOption = 'WITHOUT DEDUCTIBLE';
        }




        // $prev_pol_type = (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package (1 year OD + 1 Year TP)' : 'Standalone TP (1 year TP)'));

        //(in_array($requestData->previous_policy_type, [ 'Own-damage']) ? 'Standalone TP (1 year TP)' :

        $vehicle_sub_class = (policyProductType($productData->policy_id)->parent_id == 8) ? ($mmv->num_vehicle_sub_class == '103' ? 'C2 PCV 4 Wheeler Exceeding 6 passenger' : 'C1A PCV 4 wheeler not exceeding 6 passengers') : $mmv->vehicle_sub_class;

        $quoteRequest = [
            'quote_id'                      => '',

            'pol_plan_variant'              => ($is_package ? ($is_new ? 'PackagePolicy' : 'PackagePolicy') : ($is_liability ? ($is_new ? 'Standalone TP' : 'Standalone TP') : 'Standalone OD')),
            'pol_plan_id'                   => ($is_package ? ($is_new ? '04' : '02') : ($is_liability ? ($is_new ? '03' : '01') : '05')),

            'q_producer_code'               => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_PRODUCER_CODE'),
            'q_producer_email'              => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_PRODUCER_EMAIL'),

            'business_type_no'              => ($is_new ? '01' : '03'),

            "business_type"                 => ($is_new ? "New Business" : 'Roll Over'),

            'product_code'                  => '3184',
            'product_id'                    => 'M300000000004',
            'product_name'                  => 'Commercial Vehicle',

            'proposer_type'                 => $customer_type,

            '__finalize'                    => '0',

            'add_towing'                    => 'No',
            'add_towing_amount'             => '',
            'allowance_days_accident'       => '',
            'allowance_days_loss'           => '',

            // DISCOUNTS
            'tppd_discount'                 => $is_tppd ? 'Yes' : 'No',
            'antitheft_cover'               => $is_anti_theft ? 'Yes' : 'No',
            'automobile_association_cover'  => $is_automobile_assoc ? 'Yes' : 'No',
            'voluntary_amount'              => (string)($voluntary_excess_amt),
            // END DISCOUNT

            'cng_lpg_cover'                 => (string)($externalCNGKIT),
            'cng_lpg_si'                    => ($is_liability ? '0' : (string)($externalCNGKITSI)),

            // ASSESORIES
            "electrical_cover"               => $Electricalaccess,
            "electrical_desc"                 => "",
            'electrical_si'                 => (string)($ElectricalaccessSI),
            "non_electrical_cover"          => $NonElectricalaccess,
            "non_electrical_desc"           => "", 
            'non_electrical_si'             => (string)($NonElectricalaccessSI),
            // END ASSESORIES

            // COVERS
            'pa_named'                      => 'No',

            'pa_paid_no'                    => 0,
            'pa_paid_si'                    => 0,
            'pa_owner' => (($is_individual && !$is_od) ? ($cpa_cover ? 'true' : 'false') : 'false'),
            'pa_owner_declaration' => $driver_declaration,
            'pa_owner_tenure' => $pa_owner_tenure,

            'pa_unnamed'                    => 'No',

            'll_paid_no'                   => $LLNumberDriver +  $LLNumberConductor +$LLNumberCleaner ,
            'll_paid'                       => $is_ll_paid,
            "cover_lamps" => $is_imt_23,

            // END COVERS

            // ADDONS
            'repair_glass'                  => $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'],
            'return_invoice'                => $applicableAddon['ReturnToInvoice'],
            'rsa'                           => $applicableAddon['RoadsideAssistance'] ,
            'emergency_expense'             => $applicableAddon['EmergTrnsprtAndHotelExpense'],
            'consumbale_expense'            => $applicableAddon['ConsumablesExpenses'],
            'key_replace'                   => $applicableAddon['KeyReplacement'],
            'personal_loss'                 => $applicableAddon['LossOfPersonalBelongings'],

            'tyre_secure'                   => $applicableAddon['TyreSecure'],
            'tyre_secure_options'           => $applicableAddon['TyreSecure'] == 'Yes' ? 'REPLACEMENT BASIS' : '', // 'DEPRECIATION BASIS'

            'engine_secure'                 => $applicableAddon['EngineSecure'],

            'dep_reimburse'                 => $applicableAddon['DepreciationReimbursement'],

            'dep_reimburse_claims'          => $applicableAddon['NoOfClaimsDepreciation'],

            'ncb_protection'                => $applicableAddon['NCBProtectionCover'],
            'ncb_no_of_claims'              => '',

            // END ADDONS
            'claim_last'                    => ($is_new ? 'No' : (($requestData->is_claim == 'N' || $is_liability) ? 'No' : 'Yes')),

            // 'claim_last_amount'             => null,
            // 'claim_last_count'              => null,

            'daily_allowance'               => 'No',
            'daily_allowance_plus'          => 'No',
            'daily_allowance_limit'         => '',
            'pol_start_date'                => $policy_start_date,
            'prev_pol_end_date'             => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d')),
            'prev_pol_start_date'             => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d')),

            'dor'                           => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),
            'man_year'                      => (int)(Carbon::parse($requestData->vehicle_register_date)->format('Y')),

            'franchise_days'                => '',
            'load_fibre'                    => 'No',
            'load_imported'                 => 'No',
            'load_tuition'                  => 'No',

            // 'motor_plan_opted'              => $masterProduct->product_identifier,
            // 'motor_plan_opted_no'           => $applicableAddon['motorPlanOptedNo'],

            'plan_opted'                    => $masterProduct->product_identifier,
            'plan_opted_no'                 => $applicableAddon['motorPlanOptedNo'],

            'own_premises'                  => 'No',

            'place_reg'                     => $rto_data->txt_rtolocation_name,
            'place_reg_no'                  => $rto_data->txt_rtolocation_code,

            'pre_pol_ncb'                   => (($is_new || $noPrevPolicy) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
            'pre_pol_protect_ncb'           => "NA",

            'prev_pol_type'                 => (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package (1 year OD + 1 Year TP)' : 'Standalone TP (1 year TP)')),

            'proposer_pincode'              => (string)($proposal->pincode),

            "regno_1"                       => $vehicle_register_no[0] ?? "",
            "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")), // (string)($vehicle_register_no[1] ?? ""), (string)($vehicle_register_no[1] ?? ""),
            "regno_3"                       => $vehicle_register_no[2] ?? "",
            "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),

            'uw_discount'                   => '',
            'uw_loading'                    => '',
            'uw_remarks'                    => '',

            'vehicle_blind'                 => 'No',
            'vehicle_idv'                   => '',

            'vehicle_make'                  => $mmv->make,
            'vehicle_make_no'               => (int)($mmv->make_code),
            'vehicle_model'                 => $mmv->txt_model,
            'vehicle_model_no'              => (int)($mmv->num_model_code),
            'vehicle_variant'               => $mmv->txt_model_variant,
            'vehicle_variant_no'            => $mmv->num_model_variant_code,
        
            "gvw" => (int)((!empty($mmv->num_gross_vehicle_weight)) ? $mmv->num_gross_vehicle_weight : 0),
            "make_code" => (int)($mmv->make_code),
            "model_code" => (int)($mmv->num_model_code),
            "variant_code"                  => (int)$mmv->num_model_variant_code,
            // "variant_code" => 116337,
            "vehicle_sub_class" => $vehicle_sub_class,//$mmv->vehicle_sub_class,
            "vehicle_usage_type" => "Non Hazardous Gas Carrier",
            "vehicle_usage_type_code" => $mmv->num_vehicle_sub_class,

            'source'                        => 'P',
            'vintage_car'                   => 'No',
            'proposer_email'                => strtolower($proposal->email),
            //
            'add_pa_unnamed_si'             =>  0,
            'claim_last_amount'             => '',
            'claim_last_count'              => '',
            'nature_goods'                  => 'Non-Hazardous',
            //
            "geography_extension" => $is_Geographical,
            "geography_extension_bang" => in_array('Bangladesh', $countries) ? "Yes" : "No",
            "geography_extension_bhutan" => in_array('Bhutan', $countries) ? "Yes" : "No",
            "geography_extension_lanka" => in_array('Sri Lanka', $countries) ? "Yes" : "No",
            "geography_extension_maldives" => in_array('Nepal', $countries) ? "Yes" : "No",
            "geography_extension_nepal" => in_array('Maldives', $countries) ? "Yes" : "No",
            "geography_extension_pak" => in_array('Pakistan', $countries) ? "Yes" : "No",
     ];

     if ($breakin ) {
        $quoteRequest['carriedOutBy'] = "Yes";
    }
        $hazardousType = $proposal_additional_details['vehicle']['hazardousType'] ?? '';
        if($hazardousType == "Hazardous"){

             return [ 
                "status" => false,
                "message" => "Vehicle is Hazardous to Nature",
             ];
        }

        if($premium_type == "third_party_breakin" || $premium_type == "third_party")
        {
            $quoteRequest['cover_lamps'] = "No";
        }
      
        if ($is_posp == "Y") {
            $quoteRequest['is_posp'] = $is_posp;
            $quoteRequest['sol_id'] = $sol_id;
            $quoteRequest['q_agent_pan'] = $sol_id;
            $quoteRequest['q_office_location'] = $q_office_location;
        }

        if (!$is_new || !$noPrevPolicy) {
            $quoteRequest['no_past_pol'] = 'N';
        } else {
            $quoteRequest['no_past_pol'] = 'Y';
        }

        if (!$is_new) {
            $quoteRequest['no_past_pol'] = 'N';
        }
        if ($noPrevPolicy) {
            $quoteRequest['no_past_pol'] = 'Y';
        }
        if ($noPrevPolicy || ($noPrevPolicy && $is_liability)) {
            $quoteRequest['no_past_policy'] = 'Yes';
        } else{
            $quoteRequest['no_past_policy'] = 'No';
    
        }


        if ($applicableAddon['NCBProtectionCover'] == 'Yes') {
            $quoteRequest['ncb_no_of_claims'] = 1;
        }
        //checking last addons
        $PreviousPolicy_IsZeroDept_Cover
            = $PreviousPolicy_IsConsumable_Cover
            = $PreviousPolicy_IsReturnToInvoice_Cover
            = $PreviousPolicy_IsTyre_Cover
            = $PreviousPolicy_IsEngine_Cover
            = $PreviousPolicy_IsLpgCng_Cover
            = $is_breakin_case =  true;

        if (!empty($proposal->previous_policy_addons_list)) {

            $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list)
                ? $proposal->previous_policy_addons_list
                : json_decode($proposal->previous_policy_addons_list);

            foreach ($previous_policy_addons_list as $key => $value) {
                if ($key == 'zeroDepreciation' && $value) {
                    $PreviousPolicy_IsZeroDept_Cover = true;
                }
            }
        }
        $quoteRequest['prev_cnglpg'] = 'No';
        if (!$is_new && !$noPrevPolicy) {
            if ($is_lpg_cng) {
                $quoteRequest['prev_cnglpg'] = 'Yes';
                if (!$PreviousPolicy_IsLpgCng_Cover && !$is_liability) {
                    $is_breakin_case =  true;
                    $quoteRequest['prev_cnglpg'] = 'No';
                }
            }

            if ($is_liability) {
                $quoteRequest['prev_cnglpg'] = 'No';
            }

            $quoteRequest['prev_dep'] = 'No';
            if ($applicableAddon['DepreciationReimbursement'] == 'Yes') {
                $quoteRequest['prev_dep'] = 'Yes';
                if (!$PreviousPolicy_IsZeroDept_Cover) {
                    $quoteRequest['prev_dep'] = 'No';
                    $is_breakin_case =  true;
                }
            }
        }

        if (!$is_od) {
            if ($PAforUnnamedPassenger == 'Yes') {
                $quoteRequest['pa_unnamed'] = $PAforUnnamedPassenger;
                $quoteRequest['pa_unnamed_csi'] = '';
                $quoteRequest['pa_unnamed_no'] = (string)($mmv->num_seating_capacity);
                $quoteRequest['pa_unnamed_si'] = (string)$PAforUnnamedPassengerSI;
            }
            if ($llpaidDriver == 'Yes') {
                $quoteRequest['ll_paid'] = $llpaidDriver;
                $quoteRequest['ll_paid_no'] = $LLNumberDriver +  $LLNumberConductor +$LLNumberCleaner;
            }
            if ($PAforaddionaldPaidDriver == 'Yes') {
                $quoteRequest['pa_paid'] = $PAforaddionaldPaidDriver;
                $quoteRequest['pa_paid_no'] = 1;
                $quoteRequest['pa_paid_si'] = $PAforaddionaldPaidDriverSI;
            }
        }

        if ($is_od) {
            $quoteRequest['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
            $quoteRequest['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');

            $quoteRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
            $quoteRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
        }

        $quoteRequest = self::addAdditionalKeys($quoteRequest);

        $quoteRequest['vehicle_idv'] = (string)($idv);
        $quoteRequest['__finalize'] = '1';

        if($partially_build != false){
            $quoteRequest['chassis_idv'] = (int)($proposal_additional_details['vehicle']['chassisIdv'] ?? $quote_log_data['premium_json']['chassisIDV']) ?? 0;
            $quoteRequest['body_idv'] = (int)($proposal_additional_details['vehicle']['bodyIdv'] ?? $quote_log_data['premium_json']['bodyIDV']) ?? 0;
        }
        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer ' . $token_response['token'],
                // 'x-api-key'      => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
                'x-api-key'      => $configCreds->api_key
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium calculation - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
        $quoteResponse = $get_response['response'];


        if (!($quoteResponse && $quoteResponse != '' && $quoteResponse != null)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        $quoteResponse = json_decode($quoteResponse, true);
        ksort($quoteRequest);
        if (empty($quoteResponse)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        if(!isset($quoteResponse['status']))
        {
            return [
                'status'    => false,
                'msg'       => $quoteResponse['message_txt'] ?? ($quoteResponse['message'] ?? 'Insurer Not Reachable'),
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        if ($quoteResponse['status'] != 200) {
            if (!isset($quoteResponse['message_txt'])) {
                return [
                    'status'    => false,
                    'msg'       => 'Insurer Not Reachable',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $quoteRequest,
                    'stage'     => 'quote'
                ];
            }
            return [
                'status'    => false,
                'msg'       => $quoteResponse['message_txt'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        $quoteResponse2 = $quoteResponse;

        $premiumWebServiceId = $get_response['webservice_id'];

        $quoteResponse = $quoteResponse['data'][0]['data'];
        $pol = $quoteResponse2['data'][0]['pol_dlts'];
      

        if ($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true') {
            return [
                'status' => false,
                'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'product_identifier' => $masterProduct->product_identifier,
                'quoteResponse' => $quoteResponse
            ];
        }

        // pass idv
        $max_idv    = $quoteResponse['max_idv_body'];
        $min_idv    = $quoteResponse['min_idv_body'];

        $policy_id = $quoteResponse['policy_id'];
        $quote_id = $quoteResponse['quote_id'];
        $quote_no = $quoteResponse['quote_no'];
        $proposal_id = $quoteResponse['proposal_id'];
        $product_id = $quoteResponse['product_id'];



        $totalOdPremium = $quoteResponse['premium_break_up']['total_od_premium'];
        $totalAddons    = $quoteResponse['premium_break_up']['total_addOns'];
        $totalTpPremium = $quoteResponse['premium_break_up']['total_tp_premium'];

        $premium_without_gst = $quoteResponse['premium_break_up']['premium_value']; //net premium as without gst value use final premium
        $total_payable   = $quoteResponse['premium_break_up']['net_premium'];

        $basic_od       = $totalOdPremium['od']['basic_od'];
        $total_od       = $totalOdPremium['total_od'];
        $non_electrical = $totalOdPremium['od']['net_od'];
        $electrical     = $totalOdPremium['od']['trailer_od_prem'];
        $lpg_cng_od     = $totalOdPremium['od']['cng_lpg_od_prem'];


        // dd($totalTpPremium);

        $basic_tp       = $totalTpPremium['basic_tp'];
        $total_tp       = $totalTpPremium['total_tp'];
        $pa_unnamed     = $totalTpPremium['pa_paid_drive_prem'];
        $ll_paid        = $totalTpPremium['ll_paid_drive_prem'];
        $lpg_cng_tp     = $totalTpPremium['cng_lpg_tp_prem'];

        $pa_paid        = (int)(isset($quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem']) ? $quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem'] : 0);

        $tp_gio = (float)(isset($totalTpPremium['geography_extension_tp_prem']) ? $totalTpPremium['geography_extension_tp_prem'] : 0 );
        $od_gio = (float)(isset($totalOdPremium['od']['geography_extension_od_prem']) ? $totalOdPremium['od']['geography_extension_od_prem'] : 0 );
        $final_od_premium = $basic_od + $non_electrical + $electrical + $lpg_cng_od + $od_gio ;
        $final_tp_premium = $basic_tp + $pa_unnamed + $ll_paid + $lpg_cng_tp + $pa_paid + $tp_gio ;
        $pa_owner       = $totalTpPremium['pa_paid_drive_prem'];
        $tppd_discount  = $pol['tppd_prem'];

        // dd($totalOdPremium);

        $anti_theft_amount       = 0; #$totalOdPremium['loading_od']['theft_conversion_prem'];# ['discount_od']['atd_disc_prem'];
        $automoble_amount       = 0; #$totalOdPremium;# ['discount_od']['aam_disc_prem'];
        $voluntary_deductible   = 0; #$totalOdPremium;# ['discount_od']['vd_disc_prem'];
        $ncb_discount_amount    = 0; #$totalOdPremium;# ['discount_od']['ncb_prem'];

        $final_total_discount = $ncb_discount_amount + $anti_theft_amount + $automoble_amount + $voluntary_deductible;



        $zero_dep_amount            = $totalAddons['dep_reimburse_prem'];
        $imt_23 = $quoteResponse['premium_break_up']['total_od_premium']['loading_od']['cover_lapm_prem'];
        $rsa_amount                 = $totalAddons['rsa_prem'];
        $ncb_protect_amount         = $totalAddons['ncb_protection_prem'];
        $engine_seccure_amount      = $totalAddons['engine_secure_prem'];
        $tyre_secure_amount         = $totalAddons['tyre_secure_prem'];
        $rti_amount                 = $totalAddons['return_invoice_prem'];
        $counsumable_amount         = $totalAddons['consumbale_expense_prem'];
        $key_replacment_amount      = $totalAddons['key_replace_prem'];
        $personal_belongings_amount = $totalAddons['personal_loss_prem'];

        // $emergency_expense_amount   = $totalAddons['emergency_expense_prem'];
        $repair_glass_prem          = $totalAddons['repair_glass_prem'];

        $final_addon_amount         = $totalAddons['total_addon'];

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

        $occupation = $is_individual ? $proposal_additional_details['owner']['occupation'] : '';


        $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

        if ($proposal_additional_details['vehicle']['isVehicleFinance']) {
            $financerAgreementType = $proposal_additional_details['vehicle']['financerAgreementType'];
            $nameOfFinancer = $proposal_additional_details['vehicle']['nameOfFinancer'];
            $hypothecationCity = $proposal_additional_details['vehicle']['hypothecationCity'];
            if (isset($proposal_additional_details['vehicle']['financer_sel'][0]['name'])) {
                $nameOfFinancer = $proposal_additional_details['vehicle']['financer_sel'][0]['name'];
            }
        }

        $pucExpiry = $pucNo = '';
        if (isset($proposal_additional_details['vehicle']['pucExpiry'])) {
            $pucExpiry = Carbon::parse($proposal_additional_details['vehicle']['pucExpiry'])->format('Y-m-d');
        }

        if (isset($proposal_additional_details['vehicle']['pucNo'])) {
            $pucNo = $proposal_additional_details['vehicle']['pucNo'];
        }

        if (is_numeric($nameOfFinancer)) {
            $financeData   = DB::table("tata_aig_finance_master")
                ->where("code", $nameOfFinancer)
                ->first();
            if (!empty($financeData)) {
                $nameOfFinancer = $financeData->name;
            }
        }
        $first_name = '';
        $middle_name = '';
        $last_name = '';

        $nameArray = $is_individual ? (explode(' ', trim($proposal->first_name . ' ' . $proposal->last_name))) : explode(' ', trim($proposal->first_name));

        $first_name = $nameArray[0];

        // for TATA f_name and l_name should only contain 1 word and rest will be in m_name
        if (count($nameArray) > 2) {
            $last_name = end($nameArray);
            array_pop($nameArray);
            array_shift($nameArray);
            $middle_name = implode(' ', $nameArray);
        } else {
            $middle_name = '';
            if (env('APP_ENV') == 'local') {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '.');
            } else {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '');
            }
        }
        // dd($proposal);
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 55,
            'address_2_limit'   => 55,
            'address_3_limit'   => 55,
        ];
        $getAddress = getAddress($address_data);


        $inspection_type_self = ($is_breakin ? (($proposal->inspection_type ?? '') == 'Manual' ? 'No' : 'Yes') : 'No');
        //Self inspection #32284 end comment.
        $inspectionOverride = false;

        if (($pol["inspectionOverride"] == true) ?? '') {
            $inspectionOverride = true;
        }
        //////////////////////////////////////////////////////////////
        // dd($proposal);
        $proposalRequest = [
            'proposer_gender' => $gender,
            'proposer_marital' => $is_individual ? $proposal->marital_status : '',
            'proposer_fname' => $first_name,
            'proposer_mname' => $middle_name,
            'proposer_lname' => $last_name,
            'proposer_email' => strtolower($proposal->email),
            'proposer_mobile' => $proposal->mobile_number,
            'proposer_salutation' => $insured_prefix,
            'proposer_add1' => trim($getAddress['address_1']) ?? '',
            'proposer_add2' => trim($getAddress['address_2']) ?? '',
            'proposer_add3' => trim($getAddress['address_3']) ?? '',
            'proposer_pincode' => (string)($proposal->pincode),
            'proposer_occupation' => $occupation,
            'proposer_pan' => (string)($proposal->pan_number),
            'proposer_annual' => '',
            'proposer_gstin' => (string)($proposal->gst_number),
            'proposer_dob' => $is_individual ? Carbon::parse($proposal->dob)->format('d/m/Y') : '',

            'vehicle_puc_expiry' => $pucExpiry,
            'vehicle_puc' => $pucNo,
            'vehicle_puc_declaration' => true,
            //Self inspection changes
            "proposalInspectionOverride" => $inspectionOverride,

            'pre_insurer_name' => (($is_new || $noPrevPolicy) ? '' : $previousInsurerList->code),
            'pre_insurer_no' => (($is_new || $noPrevPolicy) ? '' : $proposal->previous_policy_number),
            'pre_insurer_address' => '', //$is_new ? '' : $proposal_additional_details['prepolicy']['previousInsuranceCompany'],

            'financier_type' => $financerAgreementType,
            'financier_name' => $nameOfFinancer,
            'financier_address' => $hypothecationCity,

            'nominee_name' => (($is_individual && !$is_od) ? ($proposal->nominee_name ?? '') : ''),
            'nominee_relation' => (($is_individual && !$is_od) ? ($proposal->nominee_relationship ?? '') : ''),
            'nominee_age' => (($is_individual && !$is_od) ? ($proposal->nominee_age ?? '') : '0'),

            'appointee_name' => '',
            'appointee_relation' => '',

            'proposal_id' => $proposal_id,
            'product_id' => $product_id,
            'quote_no' => $quote_no,

            'declaration' => 'Yes',

            'vehicle_chassis' => $proposal->chassis_number,
            'vehicle_engine' => $proposal->engine_number,

            'proposer_fullname' => ($is_individual ? ($proposal->first_name . ' ' . $proposal->last_name) : $proposal->first_name),

            '__finalize' => '1',
            'q_office_location'             => "",
            "pre_insurer_code" => (($is_new || $noPrevPolicy) ? '' : $previousInsurerList->code),



            // "upload_status"=> "uploadlater",
            // "proposer_occupation_other"=>"",
            // "proposer_aadhaar"=> "",
            // "automobile_association_expiry"=> "",	
            // "automobile_association_name"=> "",	
            // "automobile_association_no"=> "",
            // "loan_acc_no"=>"98765432",
            // // "quote_no"=>"QT/23/6300001997",	
            // "trailer_chassis"=>"",	
            // "trailer_chassis1"=>"",	
            // "trailer_make"=>"",	
            // "trailer_make1"=>"",	
            // "trailer_man_year"=>"",	
            // "trailer_modal1"=>"",	
            // "trailer_model"=>"",	
            // "trailer_regno"=>"",	
            // "trailer_regno1"=>"",	
            // "trailer_serial"=>"",	
            // "trailer_sno1"=>"",	
            // "trailer_yom1"=>"",
            // "vehicle_fc"=>"345666",	
            // "vehicle_fc_expiry"=>"08/04/2022",	
        
        ];

        if ($breakin ) {
            $proposalRequest['carriedOutBy'] = "Yes";
        }

        if ($is_posp == "Y") {
            $proposalRequest['is_posp'] = $is_posp;
            $proposalRequest['sol_id'] = $sol_id;
            $proposalRequest['q_office_location'] = $q_office_location;
        }
        if ($occupation == 'OTHER') {
            $proposalRequest['proposer_occupation_other'] = 'OTHER';
        }

        if ($is_od) {
            $proposalRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
            $proposalRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');

            $proposalRequest['ble_tp_type']             = 'Package';
            $proposalRequest['ble_tp_tenure']           = '3';
            $proposalRequest['ble_tp_no']               = $tp_policy_no;
            $proposalRequest['ble_tp_name']             = $tp_insured;
            $proposalRequest['ble_tp_start']   = Carbon::parse($tp_start_date)->format('Y-m-d');
            $proposalRequest['ble_tp_end']     = Carbon::parse($tp_end_date)->format('Y-m-d');

            $proposalRequest['ble_saod_prev_no']        = $proposal_additional_details['prepolicy']['previousPolicyNumber'];

            $proposalRequest['od_pre_insurer_name']     = '';
            $proposalRequest['od_pre_insurer_no']       = '';
            $proposalRequest['od_pre_insurer_address']  = '';
        }

        if ($requestData->vehicle_owner_type == "C") {
            $nameArray = explode(' ', trim($proposal->first_name));
            $count = count($nameArray);
            $midpoint = floor($count / 2);

            $first_name = implode(" ", array_slice($nameArray, 0, $midpoint));
            $last_name = implode(" ", array_slice($nameArray, $midpoint));
            if($count == 1){
                $first_name = $nameArray[0];
                $last_name = ' ';
            }
            $proposalRequest['proposer_fname'] = $first_name;
            $proposalRequest['proposer_mname'] = '';
            $proposalRequest['proposer_lname'] = $last_name;
        }

        if((config('IC.TATA_AIG_V2.CAR.PROPRIETORSHIP.ENABLED') == 'Y') && $requestData->vehicle_owner_type == "C"){
            if(!empty($additionalDetailsData->owner->organizationType) && $additionalDetailsData->owner->organizationType == 'Proprietorship'){
                $proposalRequest['prop_flag'] = 'true';
                $proposalRequest['prop_name'] = $proposal->proposer_ckyc_details->related_person_name;
            }else{
                $proposalRequest['prop_flag'] = 'false';
                $proposalRequest['prop_name'] = '';
            }
        }

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer ' . $token_response['token'],
                'x-api-key'      => $configCreds->api_key
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Proposal Submition - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_END_POINT_URL_PROPOSAL'), $proposalRequest, 'tata_aig_v2', $additional_data);
        $proposalResponse = $get_response['response'];


        if (!($proposalResponse && $proposalResponse != '' && $proposalResponse != null)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        }
        $proposalResponse = json_decode($proposalResponse, true);

        // dd($proposalResponse);

        if (empty($proposalResponse)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        }


        // {"message": "Endpoint request timed out"}

        if (!isset($proposalResponse['status']) || $proposalResponse['status'] != 200) {
            if (!isset($proposalResponse['message_txt'])) {
                return [
                    'status'    => false,
                    'msg'       => $proposalResponse['message'] ?? 'Insurer Not Reachable',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $proposalRequest,
                    'stage'     => 'proposal'
                ];
            }
            return [
                'status'    => false,
                'msg'       => $proposalResponse['message_txt'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        } else {

            $proposalResponse2  = $proposalResponse;
            // echo "<pre>";print_r([$proposalResponse, $proposalRequest]);echo "</pre>";die();
            $proposalResponse   = $proposalResponse['data'][0];

            $proposal->od_premium               = $total_od + $final_addon_amount;
            $proposal->tp_premium               = $total_tp;
            $proposal->cpa_premium              = $pa_owner;
            $proposal->addon_premium            = $final_addon_amount;
            $proposal->ncb_discount             = $ncb_discount_amount;
            $final_total_discount               = $final_total_discount + $tppd_discount;
            $proposal->service_tax_amount       = $premium_without_gst * 0.18;
            $proposal->total_premium            = $premium_without_gst;

            $proposal->proposal_no              = $proposalResponse['proposal_no'];
            $proposal->final_payable_amount     = $proposalResponse['premium_value'];

            $proposal->policy_start_date        = Carbon::parse($policy_start_date)->format('d-m-Y');
            $proposal->policy_end_date          = Carbon::parse($policy_end_date)->format('d-m-Y');
            $proposal->tp_start_date            = Carbon::parse($tp_start_date)->format('d-m-Y');
            $proposal->tp_end_date              =  Carbon::parse($tp_end_date)->format('d-m-Y');
            // dd($proposalResponse['payment_id']);
            $tata_aig_v2_data = [
                'quote_no'       => $proposalResponse['quote_id'],
                'proposal_no'    => $proposalResponse['proposal_no'],
                'proposal_id'    => $proposalResponse['proposal_id'],
                'payment_id'     => $proposalResponse['payment_id'],
                'document_id'    => '', #$proposalResponse['document_id'],
                'policy_id'      => $proposalResponse['policy_id'],
                'master_policy_id' => $productData->policy_id,
            ];

       
             //check inspection tag 
            $inspectionFlag = $pol["inspectionFlag"] ?? "";

            $isBreakinInspectionRequired = $inspectionFlag == "Y" ? true : false;

            if ($isBreakinInspectionRequired && !(config('IC.TATA_AIG.V2.GCV.INSPECTION_ENABLED') == 'Y')) {
                return [
                    'status'    => false,
                    'msg'       => 'Inspection is not allowed',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $proposalRequest,
                    'Request'   => $proposalResponse,
                    'stage'     => 'proposal'
                ];
            } else if ($isBreakinInspectionRequired && empty($proposalResponse['ticket_number'])) {
                if (!empty($proposalResponse['ticket_desc'])) {
                    $prefix = 'Lead already exists with id ';
                    if (strpos($proposalResponse['ticket_desc'], $prefix) !== false) {
                        $ticketNumber = str_replace($prefix, '', $proposalResponse['ticket_desc']);
                        $proposalResponse['ticket_number'] = $ticketNumber;
                    }
                }

                if (empty($proposalResponse['ticket_number'])) {
                    return [
                        'status'    => false,
                        'msg'       => 'Inspection Ticket Number not generated. Kindly reach each out Tata AIG.',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'Request'   => $proposalRequest,
                        'Request'   => $proposalResponse,
                        'stage'     => 'proposal'
                    ];
                }
            }

            $proposal_additional_details['tata_aig_v2'] = $tata_aig_v2_data;

            $proposal->additional_details = json_encode($proposal_additional_details);
            $proposal->additional_details_data = $proposal_additional_details;
            // $proposal->is_breakin_case = ($isBreakinInspectionRequired || ($is_breakin_case) ? 'Y' : 'N');
            $proposal->save();




            userProposal::where(['user_product_journey_id' => $proposal->user_product_journey_id, 'user_proposal_id' => $proposal->user_proposal_id])
                ->update([
                    'od_premium' => $total_od - $final_addon_amount,
                    'tp_premium' => $total_tp,
                    'ncb_discount' => $ncb_discount_amount,
                    'electrical_accessories' => $electrical,
                    'non_electrical_accessories' => $non_electrical
                ]);

            $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
            $data['ic_id'] = $productData->policy_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;

            updateJourneyStage($data);

            TataAigPremiumDetailController::saveV2PremiumDetails($premiumWebServiceId);

            $finsall = new \App\Http\Controllers\Finsall\FinsallController();
            $finsall->checkFinsallAvailability('tata_aig', 'cv', $premium_type, $proposal);

            if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_ENABLED_TATA_AIG') == 'Y') {
                try {
                    $is_breakin_case = ($isBreakinInspectionRequired ? 'Y' : 'N');

                    // $validateCKYC = self::validateCKYC($proposal, $proposalResponse, $get_response, $is_breakin_case);

                    if (config('constants.IcConstants.tata_aig_v2.IS_NEW_CKYC_FLOW_ENABLED_FOR_TATA_AIG_V2') == 'Y') {
                        $webserviceData = $get_response;
                        $proposalSubmitResponse = $proposalResponse;

                        $validateCKYC = ckycVerifications(compact('proposal', 'proposalSubmitResponse', 'webserviceData', 'is_breakin_case'));
                        // dd($validateCKYC);

                        $validateCKYCJSON = $validateCKYC;
                        if (!$validateCKYC['status']) {
                            return response()->json($validateCKYC);
                        }
                    } else {
                        $validateCKYC = self::validateCKYC($proposal, $proposalResponse, $get_response, $is_breakin_case);

                        $validateCKYCJSON = $validateCKYC->getOriginalContent();

                        if (!$validateCKYCJSON['status']) {
                            return $validateCKYC;
                        }
                    }

                    if ($isBreakinInspectionRequired || ($is_breakin_case == 'Y')) {
                        if (!empty($validateCKYCJSON['data']['otp_id'] ?? '')) {
                            $additionalDetailsData = $proposal->additional_details_data;

                            $additionalDetailsData['is_breakin_case'] = $is_breakin_case;
                            $additionalDetailsData['ticket_number'] = $proposalResponse['ticket_number'];

                            $new_proposal_data = [
                                'additional_details_data' => json_encode($additionalDetailsData)
                            ];
                            UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                                ->update($new_proposal_data);
                        } else {
                            self::createTataBreakindata($proposal, $proposalResponse['ticket_number']);
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
            if (config('constants.IS_CKYC_ENABLED_TATA_AIG') != 'Y' && !$is_liability) {
                if (($is_breakin && !$inspectionOverride && empty($proposalResponse['payment_id'])) || $isBreakinInspectionRequired) {
                    UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                        ->update([
                            'is_breakin_case' => 'Y',
                        ]);
                    $proposal->refresh();
                    self::createTataBreakindata($proposal, $proposalResponse['ticket_number']);
                    $proposal->refresh();
                }
            }
            $submitProposalResponse = [
                'status' => true,
                'msg' => 'Proposal Submitted Successfully..!',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $proposal->user_product_journey_id,
                    'proposalNo' => $proposalResponse['proposal_no'],
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => $proposal->is_breakin_case,
                    'isBreakinCase' => $proposal->is_breakin_case,
                    'inspection_number' => (isset($proposalResponse['ticket_number']) ? $proposalResponse['ticket_number'] : '')
                ]
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

    public static function checkTataAigMMV($productData, $version_id, $requestData)
    {

        // $payload = DB::table('tata_aig_v2_manufacturer_master AS tam')
        //     ->leftJoin('tata_aig_v2_vehicle_model_master AS tavm', 'tavm.num_manufacture_cd', '=', 'tam.num_manufacturercode')
        //     ->leftJoin('tata_aig_v2_model_master AS tamm', 'tamm.num_model_code', '=', 'tavm.num_model_code')
        //     ->where('tamm.num_model_variant_code', '100070')
        //     ->first();

        // $payload->ic_version_code = '100070';

        // return [
        //     'status' => 1,
        //     'data'  => (array)$payload
        // ];






        $product_sub_type_id = $productData->product_sub_type_id;

        $parent_id = get_parent_code($productData->product_sub_type_id);

        $mmv = get_mmv_details($productData, $version_id, 'tata_aig_v2', ($parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL));
        if ($mmv["status"] == 1) {
            $mmv_data = $mmv["data"];
        } else {
            return [
                "premium_amount" => "0",
                "status" => false,
                "message" => $mmv["message"],
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);
        $getVehiclePartialBuild = getVehiclePartialBuild($mmv_data->fyntune_version['version_id']);
        $mmv = array_merge($mmv, $getVehiclePartialBuild);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == "") {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => "Vehicle Not Mapped",
            ]);
        } elseif ($mmv_data->ic_version_code == "DNE") {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" =>
                "Vehicle code does not exist with Insurance company",
            ]);
        }

        return (array)$mmv;
    }

    public static function getToken($enquiryId, $productData, $transaction_type = 'proposal')
    {
        $configCreds = self::getTataAigV2CvCreds();

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Token Generation',
            'transaction_type'  => $transaction_type,
            'productName'       => $productData->product_name,
            'type'              => 'token'
        ];
        // dd($configCreds);
        $tokenRequest = [
            'grant_type'    => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_GRANT_TYPE'),
            'scope'         => $configCreds->scope,
            'client_id'     => $configCreds->client_id,
            'client_secret' => $configCreds->client_secret,
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_END_POINT_URL_TOKEN'), $tokenRequest, 'tata_aig_v2', $additional_data);
        $tokenResponse = $get_response['response'];

        if ($tokenResponse && $tokenResponse != '' && $tokenResponse != null) {
            $tokenResponse = json_decode($tokenResponse, true);

            if (!empty($tokenResponse)) {
                if (isset($tokenResponse['error'])) {
                    return [
                        'status'    => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg'       => $tokenResponse['error'],
                        'stage'     => 'token'
                    ];
                } else {
                    return [
                        'status'    => true,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'token'     => $tokenResponse['access_token'],
                        'stage'     => 'token'
                    ];
                }
            } else {
                return [
                    'status'    => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg'       => 'Insurer Not Reachable',
                    'stage'     => 'token'
                ];
            }
        } else {
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg'       => 'Insurer Not Reachable',
                'stage'     => 'token'
            ];
        }
    }

    public static function getApplicableAddons($masterProduct, $is_liability)
    {

        $productData    = getProductDataByIc($masterProduct->master_policy_id);

        $is_zero_dep = ($productData->zero_dep == '0');

        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'No';
        $applicableAddon['DepreciationReimbursement'] = $is_zero_dep ? 'Yes' : 'No';
        $applicableAddon['YesOfClaimsDepreciation'] = '';
        $applicableAddon['ConsumablesExpenses'] = 'No';
        $applicableAddon['LossOfPersonalBelongings'] = 'No';
        $applicableAddon['EngineSecure'] = 'No';
        $applicableAddon['TyreSecure'] = 'No';
        $applicableAddon['KeyReplacement'] = 'No';
        $applicableAddon['RoadsideAssistance'] = 'Yes';
        $applicableAddon['ReturnToInvoice'] = 'No';
        $applicableAddon['NCBProtectionCover'] = 'No';
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'No';
        $applicableAddon['NoOfClaimsDepreciation'] = $is_zero_dep ? '2' : ''; #
        $applicableAddon['motorPlanOptedNo'] = '';

        if ($is_liability) {
            $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'No';
            $applicableAddon['DepreciationReimbursement'] = 'No';
            $applicableAddon['NoOfClaimsDepreciation'] = '';
            $applicableAddon['ConsumablesExpenses'] = 'No';
            $applicableAddon['LossOfPersonalBelongings'] = 'No';
            $applicableAddon['EngineSecure'] = 'No';
            $applicableAddon['TyreSecure'] = 'No';
            $applicableAddon['KeyReplacement'] = 'No';
            $applicableAddon['RoadsideAssistance'] = 'No';
            $applicableAddon['ReturnToInvoice'] = 'No';
            $applicableAddon['NCBProtectionCover'] = 'No';
            $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'No';
            $applicableAddon['NoOfClaimsDepreciation'] = ''; #
            $applicableAddon['motorPlanOptedNo'] = '';
        }
        return $applicableAddon;
    }

    public static function validaterequest($response)
    {
        if (!($response && $response != '' && $response != null)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
            ];
        }
        $response = json_decode($response, true);

        if (empty($response)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
            ];
        }

        if (isset($response['status']) && $response['status'] != 200) {
            if (!isset($response['message_txt'])) {
                return [
                    'status'    => false,
                    'msg'       => 'Insurer Not Reachable',
                ];
            }
            return [
                'status'    => false,
                'msg'       => $response['message_txt'],
            ];
        }elseif(isset($response['status']) && $response['status'] == 200 && isset($response['data']['policy_no']) && $response['data']['policy_no'] == null){
            if (!isset($response['message_txt'])) {
                return [
                    'status'    => false,
                    'msg'       => 'Insurer Not Reachable',
                ];
            }
            return [
                'status'    => false,
                'msg'       => 'Policy Number Not Found',
            ];
        } else {
            return [
                'status'    => isset($response['data']) ? true : false,
                'data'      => $response['data'] ?? 'Insurer Not Reachable'
            ];
        }
    }

    public static function changeToUAT($mmv)
    {
        $uat_model_master = [
            "1816" => [
                "txt_manufacturername" => "HONDA",
                "txt_model_name" => "BRIO",
                "txt_model_variant" => "1.2 E MT",
                "txt_fuel_type" => "PETROL",
                "num_gross_vehicle_weight" => "0",
                "num_cubic_capacity" => "1198",
                "num_seating_capacity" => "5",
                "num_manufacturercode" => "113",
                "num_model_variant_code" => "100007",
                "num_model_code" => "10001",
            ],
            "68" => [
                "txt_manufacturername" => "HONDA",
                "txt_model_name" => "CITY",
                "txt_model_variant" => "1.3 LXI",
                "txt_fuel_type" => "PETROL",
                "num_gross_vehicle_weight" => "0",
                "num_cubic_capacity" => "1343",
                "num_seating_capacity" => "5",
                "num_manufacturercode" => "113",
                "num_model_variant_code" => "100070",
                "num_model_code" => "10005",
            ],
            "1025310" => [
                "txt_manufacturername" => "MARUTI",
                "txt_model_name" => "ALTO",
                "txt_model_variant" => "LXI",
                "txt_fuel_type" => "PETROL",
                "num_gross_vehicle_weight" => "0",
                "num_cubic_capacity" => "796",
                "num_seating_capacity" => "5",
                "num_manufacturercode" => "125",
                "num_model_variant_code" => "103321",
                "num_model_code" => "10293",
            ],
            "1033527" => [
                "txt_manufacturername" => "BMW",
                "txt_model_name" => "6 SERIES",
                "txt_model_variant" => "640 D CONVERTIBLE",
                "txt_fuel_type" => "DIESEL",
                "num_gross_vehicle_weight" => "0",
                "num_cubic_capacity" => "2993",
                "num_seating_capacity" => "4",
                "num_manufacturercode" => "105",
                "num_model_variant_code" => "101174",
                "num_model_code" => "10089",
            ],
            "1022" => [
                "txt_manufacturername" => "TATA MOTORS",
                "txt_model_name" => "INDIGO MARINA",
                "txt_model_variant" => "LS",
                "txt_fuel_type" => "DIESEL",
                "num_gross_vehicle_weight" => "0",
                "num_cubic_capacity" => "1405",
                "num_seating_capacity" => "4",
                "num_manufacturercode" => "140",
                "num_model_variant_code" => "100344",
                "num_model_code" => "10034",
            ],
        ];

        if (isset($uat_model_master[$mmv->ic_version_code])) {
            $mmv->txt_fuel = $uat_model_master[$mmv->ic_version_code]['txt_fuel_type'];
            $mmv->manufacturer = $uat_model_master[$mmv->ic_version_code]['txt_manufacturername'];
            $mmv->vehiclemodel = $uat_model_master[$mmv->ic_version_code]['txt_model_name'];
            $mmv->txt_varient = $uat_model_master[$mmv->ic_version_code]['txt_model_variant'];

            $mmv->seatingcapacity = $uat_model_master[$mmv->ic_version_code]['num_seating_capacity'];
            $mmv->cubiccapacity = $uat_model_master[$mmv->ic_version_code]['num_cubic_capacity'];
            $mmv->grossvehicleweight = $uat_model_master[$mmv->ic_version_code]['num_gross_vehicle_weight'];
            $mmv->manufacturercode = $uat_model_master[$mmv->ic_version_code]['num_manufacturercode'];
            $mmv->num_parent_model_code = $uat_model_master[$mmv->ic_version_code]['num_model_code'];
            $mmv->vehiclemodelcode = $uat_model_master[$mmv->ic_version_code]['num_model_variant_code'];
        }
        return $mmv;
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
       
        if(config('constants.IS_CKYC_ENABLED_TATA_AIG') != 'Y') {
            $ckyc_response['data']['verification_status'] = true;
        }
        if ($ckyc_response['data']['verification_status'] == true) {
            return response()->json([
                'status' => true,
                'ckyc_status' => true,
                'msg' => 'Proposal Submitted Successfully..!',
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
                        'is_breakin' => 'N', //$is_breakin_case,
                        'isBreakinCase' => 'N', //$is_breakin_case,
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

    public static function createTataBreakindata($proposalData, $ticketNumber)
    {
        if ($proposalData->is_ckyc_verified == 'Y' && $proposalData->is_breakin_case == 'N') {
            UserProposal::where(['user_proposal_id' => $proposalData->user_proposal_id])
                ->update([
                    'is_breakin_case' => 'Y'
                ]);
            updateJourneyStage([
                'user_product_journey_id' => $proposalData->user_product_journey_id,
                'ic_id' => $proposalData->ic_id,
                'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                'proposal_id' => $proposalData->user_proposal_id
            ]);
        } elseif ($proposalData->is_ckyc_verified == 'N' && $proposalData->is_breakin_case == 'Y'){
            UserProposal::where(['user_proposal_id' => $proposalData->user_proposal_id])
                ->update([
                    'is_breakin_case' => 'N'
                ]);
            updateJourneyStage([
                'user_product_journey_id' => $proposalData->user_product_journey_id,
                'ic_id' => $proposalData->ic_id,
                'stage' => STAGE_NAMES['PROPOSAL_DRAFTED'],
                'proposal_id' => $proposalData->user_proposal_id
            ]);
            //CKYC Bypass here.
            if(config('constants.IS_CKYC_ENABLED_TATA_AIG') != 'Y'){
                UserProposal::where(['user_proposal_id' => $proposalData->user_proposal_id])
                ->update([
                    'is_breakin_case' => 'Y'
                ]);
                updateJourneyStage([
                    'user_product_journey_id' => $proposalData->user_product_journey_id,
                    'ic_id' => $proposalData->ic_id,
                    'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                    'proposal_id' => $proposalData->user_proposal_id
                ]);
            }
        }

        CvBreakinStatus::updateOrCreate(
            ['user_proposal_id'  => $proposalData->user_proposal_id],
            [
                'ic_id'             => $proposalData->ic_id,
                'breakin_number'    => $ticketNumber,
                'breakin_id'        => $ticketNumber,
                'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                'payment_end_date'  => Carbon::today()->addDay(3)->toDateString(),
                'created_at'        => Carbon::now()->toDateTimeString()
            ]
        );
        // updateJourneyStage([
        //     'user_product_journey_id' => $proposalData->user_product_journey_id,
        //     'ic_id' => $proposalData->ic_id,
        //     'stage' => STAGE_NAMES['INSPECTION_PENDING'],
        //     'proposal_id' => $proposalData->user_proposal_id
        // ]);
    }

    public static  function addAdditionalKeys($quoteRequest)
    {
        $quoteRequest = array_merge($quoteRequest, [
            "add_pa" => "No",
            "add_pa_si" => "",
            "add_tppd" => "No",
            "add_tppd_si" => "",
            "add_transport" => "No",
            "add_transport_si" => "",
            "add_vehicle_towing" => "No",
            "add_vehicle_towing_amount" => "",
            "aoa_si" => "",
            "aoy_si" => "",
            "body_idv" => 0,
            "chassis_idv" => 0,

            "commerical_private" => "No",
            "confined_ownsite" => "No",

            // "cover_lamps" => "No",
            "cvplying_cluster" => "",


            "email" => "test@t.com",
            // "emergency_expense_si" => 55000,
            "emergency_medical" => "No",
            "emergency_medical_si" => "",
            "emi_protector" => "No",
            "emi_protector_si" => "",
            "fleetCode" => 0,
            "fleetName" => "",
            "fleetOpted" => false,

            // GEOGRAPHICAL EXTENTION
           
            // END GEOGRAPHICAL EXTENTION



            // "gvw" => 25000,
            "hired_hirer_35" => "No",
            "idemnity_hirer_36" => "No",
            "idemnity_hirer_44" => "No",
            "idemnity_hirer_45" => "No",
            "imposed_excess" => "",
            // "key_replace_si" => 2,
            "liability_theft" => "",

            "ll_operator" => "No",
            "ll_operator_no" => "",


            "ll_pass" => "No",
            "ll_pass_accident" => "No",
            "ll_pass_accident_no" => "",
            "ll_pass_fare" => "No",
            "ll_pass_fare_no" => "",
            "ll_pass_no" => "",
            "ll_pass_non_fare" => "",
            "ll_pass_non_fare_emp" => "No",
            "ll_pass_non_fare_emp_no" => "",
            "ll_pass_non_fare_no" => "",


            "ll_wc" => "No",
            "ll_wc_no" => "",

            "loss_equipments" => "No",
            "loss_income" => "No",
            "loss_income_days" => "",
            // "make_code" => 450,
            "mobile_no" => "9321211533",
            // "model_code" => 11778,
            "ncb_protection" => "No",
            // "no_past_policy" => "No",
            "optionForCalculation" => "Yearly",
            "overturning_load" => "No",

            // "personal_loss_si" => 10000,

            // // PREVIOUS ADDON TAGS
            // "prev_cnglpg" => "No",
            // "prev_dep" => "No",
            "prev_engine" => "No",
            "prev_rti" => "No",
            "prev_tyre" => "No",
            // // END PREVIOUS ADDON TAGS

            //PROPOSAL TAGS
            // "proposer_fname" => "Mahendra",
            // "proposer_fullname" => "",
            // "proposer_lname" => "Saru",
            // "proposer_mname" => "",
            // "proposer_mobile" => "8754285666",
            // "proposer_salutation" => "Mr",
            //END PROPOSAL TAGS

            "rim_guard" => "No",
            "rim_guard_si" => "",
            "route_from" => "",
            "route_to" => "",
            "special_regno" => "No",
            "sub_body_type" => "",
            "system_discount" => "",
            "theft_conversion" => "No",
            "total_idv" => "",
            "trailer_attach" => "No",
            "trailer_idv" => "",
            "trailer_idv1" => "",
            "trailer_idv2" => "",
            "trailer_no" => "",
            // "variant_code" => 116337,
            "vehicle_built_type" => "",
            "vehicle_carrying" => "2",
            "vehicle_city" => "",
            "vehicle_class" => "",
            "vehicle_color" => "",
            "vehicle_mobility" => "",
            "vehicle_permit" => "",
            // "vehicle_sub_class" => "A1 GCV Public carriers other than 3 wheelers",
            // "vehicle_usage_type_code" => "137",
        ]);
        return $quoteRequest;
    }

    //
    public static function getTataAigV2CvCreds()
    {
        return (object)[
            'scope' => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_SCOPE'),
            'client_id' => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_CLIENT_SECRET'),
            'api_key' => config('constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_XAPI_KEY'),
        ];
    }

    public static function submitPcv($proposal, $request)
    {

        $configCreds = self::getTataAigV2CvCreds();
        $enquiryId      = customDecrypt($request["userProductJourneyId"]);
        $requestData    = getQuotation($enquiryId);
        $productData    = getProductDataByIc($request["policyId"]);
        $masterProduct  = MasterProduct::where("master_policy_id", $productData->policy_id)
            ->first();
        $premium_type   = DB::table("master_premium_type")
            ->where("id", $productData->premium_type_id)
            ->pluck("premium_type_code")
            ->first();

        $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
            ->first();

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $breakin = in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin']);

        $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
        $is_individual  = $requestData->vehicle_owner_type == "I" ? true : false;
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
        $is_three_months    = (($premium_type == 'short_term_3') ? true : false);
        $is_six_months      = (($premium_type == 'short_term_6') ? true : false);
        $is_breakin     = ($requestData->business_type == "breakin");
        $is_breakin_short_term = ($premium_type == "short_term_3_breakin") ? true : false;
        $noPrevPolicy   = ($requestData->previous_policy_type == 'Not sure');
        $any_breakin_case = ($is_breakin || $is_breakin_short_term || $breakin);
        //Handling the new ckyc breakin case 
        $disable_breakin_ckyc = config('DISABLE_CV_BREAKIN_CKYC_HANDLE', 'N');
        if ($any_breakin_case && $disable_breakin_ckyc != 'Y') {
            $exists = CvBreakinStatus::where('user_proposal_id', $proposal->user_proposal_id)
            ->whereNotNull('breakin_number')
            ->whereNotNull('breakin_id')
            ->exists();
            
            $exists_pno = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
            ->where('is_ckyc_verified', 'N')
            ->exists();
            
            if($exists && $exists_pno){
                $is_breakin_case = 'Y';
                $proposalSubmitResponse = json_decode($proposal->additional_details_data);
                $webserviceData = ($proposalSubmitResponse->webserviceData);

                //Update pan number
                $proposalSubmitResponse->owner->panNumber = $proposal->pan_number;

                //Call CKYC
                $validateCKYC = ckycVerifications(compact('proposal', 'proposalSubmitResponse', 'webserviceData', 'is_breakin_case'));
                $validateCKYCJSON = $validateCKYC;
                if ($validateCKYC['status']) {
                    UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                    ->update([
                        'is_breakin_case' => 'Y'
                    ]);
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        'proposal_id' => $proposal->user_proposal_id
                    ]);
                    return [
                        'status' => true,
                        'ckyc_status' => true,
                        'message' => STAGE_NAMES['INSPECTION_PENDING'],
                        'webservice_id' => $webserviceData->webservice_id,
                        'table' => $webserviceData->table,
                        'data' => [
                            'verification_status' => true,
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal->user_product_journey_id,
                            'proposalNo' => $proposalSubmitResponse->proposalSubmit->proposal_no,
                            'finalPayableAmount' => $proposal->final_payable_amount,
                            'is_breakin' => 'Y',
                            'inspection_number' => $proposalSubmitResponse->proposalSubmit->ticket_number,
                            'kyc_verified_using' => $validateCKYC['data']['kyc_verified_using'],
                            'kyc_status' => true
                        ]
                    ];
                }else{
                    return response()->json($validateCKYC);
                }
            }
        }

        $idv            = $quote_log_data->idv;

        $check_mmv      = self::checkTataAigPCVMMV($productData, $requestData->version_id, $requestData);

        if (!$check_mmv['status']) {
            return $check_mmv;
        }

        $mmv            = (object)$check_mmv['data'];

        if (config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_MMV_CHANGE_TO_UAT') == 'Y') {
            $mmv = self::changeToUAT($mmv);
        }
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $customer_type  = $is_individual ? "Individual" : "Organization";
        $current_date = date("Y-m-d");

        if ($is_new)
        {
            $policy_start_date = date("Y-m-d", strtotime($requestData->vehicle_register_date));

            if ($is_liability)
            {
                $policy_start_date = date("Y-m-d", strtotime($policy_start_date . "+ 1day"));
            }

            $policy_end_date = date("Y-m-d", strtotime($policy_start_date . "- 1 days + 1 year"));
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = $policy_end_date;

            $vehicle_age = "0";
        }
        else
        {
            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == "New" ? date("Y-m-d") : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = $interval->y * 12 + $interval->m + 1;
            $vehicle_age = $interval->y;

            $motor_manf_date = "01-" . $requestData->manufacture_year;

            $policy_start_date = date("Y-m-d", strtotime($requestData->previous_policy_expiry_date . " + 1 days"));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date))
            {
                $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
            }

            $policy_end_date = date("Y-m-d", strtotime($policy_start_date . " - 1 days + 1 year"));
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = $policy_end_date;
        }

        if($is_three_months)
        {
            $policy_end_date    = date('Y-m-d', strtotime($policy_start_date . ' - 1 days + 3 month'));
            $tp_end_date        = $policy_end_date;
        }
        if($is_six_months)
        {
            $policy_end_date    = date('Y-m-d', strtotime($policy_start_date . ' - 1 days + 6 month'));
            $tp_end_date        = $policy_end_date;
        }
        if($is_breakin_short_term)
        {
            $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 2 days + 3 month'));
            $policy_start_date = date('Ymd', strtotime($policy_start_date . ' - 1 days'));

            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = $policy_end_date;
    
            if ($requestData->previous_policy_type == 'Not sure') {
                $policy_start_date = date('Ymd', strtotime($policy_start_date . ' + 1 days '));
                $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 3 month'));
                $tp_start_date      =  $policy_start_date;
                $tp_end_date        = $policy_end_date;
            }
        }

        $policy_end_date    = date('Ymd', strtotime($policy_end_date));
       

        $vehicle_register_no = explode("-", $proposal->vehicale_registration_number);

        $previousInsurerList = PreviousInsurerList::where([
            "company_alias" => 'tata_aig_v2',
            "name" => $proposal->insurance_company_name,
        ])->first();


        $additionalDetailsData = json_decode($proposal->additional_details); 
        // addon
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAPaidDriverConductorCleaner = $PAforaddionaldPassenger = $llpaidDriver = $NonElectricalaccess = $is_Geographical = "No";

        $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = '';

        $is_anti_theft = false;
        $is_automobile_assoc = false;
        $is_anti_theft_device_certified_by_arai = "false";
        $is_tppd = false;
        $is_voluntary_access = 'No';
        $voluntary_excess_amt = '';

        $is_electrical = false;
        $is_non_electrical = false;
        $is_lpg_cng = false;

        $is_ll_paid = "No";
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $is_electrical = true;
                $Electricalaccess = "Yes";
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $is_non_electrical = true;
                $NonElectricalaccess = "Yes";
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $is_lpg_cng = true;
                $externalCNGKIT = "Yes";
                $externalCNGKITSI = $value['sumInsured'];
                if ($mmv->txt_fuel_type != ' External CNG' || $mmv->txt_fuel_type != ' External LPG') {
                    $mmv->txt_fuel_type = 'External CNG';
                    $mmv->num_fuel_type = '5';
                }
            }

            if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
                $PAPaidDriverConductorCleaner = "Yes";
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }
        $LLNumberDriver = 0;
        $LLNumberConductor = 0;
        $LLNumberCleaner = 0;
        $countries = [];

        foreach ($additional_covers as $key => $value) {
            if (in_array('PA paid driver/conductor/cleaner', $value)) {
                $PAforaddionaldPaidDriver = "Yes";
                $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            }

            if ($value['name'] == 'LL paid driver/conductor/cleaner') {
                $LLNumberDriver = $value['LLNumberDriver'] ?? 0;
                $is_ll_paid = "Yes";
                $LLNumberCleaner = $value['LLNumberCleaner'] ?? 0;
                $is_ll_paid = "Yes";
                $LLNumberConductor = $value['LLNumberConductor'] ?? 0;
                $is_ll_paid = "Yes";

                $llpaidDriver = (in_array('DriverLL', $value['selectedLLpaidItmes']) || in_array('CleanerLL', $value['selectedLLpaidItmes']) || in_array('ConductorLL', $value['selectedLLpaidItmes'])) ? 'Yes' : 'No';
            }
            if($value['name'] == 'LL paid driver'){
                $is_ll_paid = "Yes";
                $llpaidDriver = 'Yes';
                $LLNumberDriver = 1;
            }
            if ($value['name'] == 'Geographical Extension') {
                $countries = $value['countries'];
                $is_Geographical = "Yes";
            }
        }

        foreach ($discounts as $key => $discount) {
            if ($discount['name'] == 'anti-theft device' && !$is_liability) {
                $is_anti_theft = true;
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            if ($discount['name'] == 'voluntary_insurer_discounts' && isset($discount['sumInsured'])) {
                $is_voluntary_access = "Yes";
                $voluntary_excess_amt = $discount['sumInsured'];
            }

            if ($discount['name'] == 'TPPD Cover' && !$is_od) {
                $is_tppd = true;
            }
        }

        if (config('constants.IcConstants.tata_aig_v2.NO_VOLUNTARY_DISCOUNT') == 'Y') {
            $is_voluntary_access = false;
            $voluntary_excess_amt = '';
        }

        // cpa vehicle

        $proposal_additional_details = json_decode($proposal->additional_details, true);


        // cpa vehicle
        $driver_declaration = "None";
        $pa_owner_tenure = '';
        if (isset($selected_addons->compulsory_personal_accident[0]["name"])) {
            $cpa_cover = true;
            $driver_declaration = "None";

            $tenure = 1;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : $tenure;
            if ($tenure === 3 || $tenure === '3') {
                $pa_owner_tenure = '3';
            } else {
                $pa_owner_tenure = '1';
            }
        } else {

            $cpa_cover = false;
            if ($customer_type == "Individual") {
                if (isset($proposal_additional_details["prepolicy"]["reason"]) && $proposal_additional_details["prepolicy"]["reason"] == "I have another motor policy with PA owner driver cover in my name") {

                    $driver_declaration = "Other motor policy with CPA";
                } elseif (isset($proposal_additional_details["prepolicy"]["reason"]) && in_array($proposal_additional_details["prepolicy"]["reason"], ["I have another PA policy with cover amount greater than INR 15 Lacs", 'I have another PA policy with cover amount of INR 15 Lacs or more'])) {
                    $driver_declaration = "Insured has standalone PA cover >= 15 lakhs";
                } elseif (isset($proposal_additional_details["prepolicy"]["reason"]) && $proposal_additional_details["prepolicy"]["reason"] == "I do not have a valid driving license.") {
                    $driver_declaration = "Owner driver does not hold valid Driving license";
                } else {
                    $driver_declaration = "None";
                }
            }
        }
        // $applicableAddon = getApplicableAddonstata($masterProduct, $is_liability, $interval);
        // if ($is_zero_dep && $vehicle_age >= 5) {
        //     return [
        //         'status' => false,
        //         'message' => 'Zero Dept is not available above 5 Year Vehicle',
        //     ];
        // }
        $applicableAddon['TyreSecure'] = "No";
        $applicableAddon['EngineSecure'] = "No";
        $applicableAddon['NoOfClaimsDepreciation'] = "No";
        $applicableAddon['ReturnToInvoice'] = "No";
        $applicableAddon['KeyReplacement'] = "No";
        $applicableAddon['LossOfPersonalBelongings'] = "No";
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "No";
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "No";
        $applicableAddon['ConsumablesExpenses'] = "No";
        $applicableAddon['RoadsideAssistance'] = "No";
        $applicableAddon['NCBProtectionCover'] = "No";
        $applicableAddon['DepreciationReimbursement'] = "No";
        foreach ($addons as $key => $value) {
            if (!$is_liability && in_array('Road Side Assistance', $value)) {
                $applicableAddon['RoadsideAssistance'] = "Yes";
            }

            if (!$is_liability && in_array('NCB Protection', $value)) {
                $applicableAddon['NCBProtectionCover'] = "Yes";
            } 
            if (!$is_liability && in_array('Consumable', $value)) {
                $applicableAddon['ConsumablesExpenses'] = "Yes";
            }

            if ($value['name'] == 'Zero Depreciation' && $productData->zero_dep == '0') {
                $applicableAddon['DepreciationReimbursement'] = "Yes";
            }
        }

        if ($is_new || $requestData->applicable_ncb < 25) { //NCB protection cover is not allowed for NCB less than or equal to 20%
            $applicableAddon['NCBProtectionCover'] = "No";
        }

        $DepreciationReimbursement = $ac_opted_in_pp = "N";
        if($is_zero_dep)
        {
            $DepreciationReimbursement  = 'Yes';
            $NoOfClaimsDepreciation     = "2";
        }
        else
        {
            $DepreciationReimbursement  = 'No';
            $NoOfClaimsDepreciation     = "";
        }

        if($is_liability){
            $DepreciationReimbursement = 'No';
            $NoOfClaimsDepreciation = "" ;
        }
        // END ADDONS

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_aadhar = '';
        $pos_pan    = '';
        $sol_id     = ""; //config('constants.IcConstants.tata_aig.SOAL_ID');
        $is_posp = 'N';
        $q_office_location = 0;

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type', 'P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log_data->idv <= 5000000) {
            if (!empty($pos_data->pan_no)) {
                $is_posp = 'Y';
                $sol_id = $pos_data->pan_no;
                $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.cv.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
            }
        } elseif (config('constants.motor.constants.IcConstants.tata_aig_v2.cv.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2') == 'Y') {
            $is_posp = 'Y';
            $sol_id     = '55554'; //'840372';
            $q_office_location = 90431; //90200;
        } else {
            $is_pos = 'N';
        }

        if ($is_od) {
            $tp_insured         = $proposal_additional_details['prepolicy']['tpInsuranceCompany'];
            $tp_insurer_name    = $proposal_additional_details['prepolicy']['tpInsuranceCompanyName'];
            $tp_start_date      = $proposal_additional_details['prepolicy']['tpStartDate'];
            $tp_end_date        = $proposal_additional_details['prepolicy']['tpEndDate'];
            $tp_policy_no       = $proposal_additional_details['prepolicy']['tpInsuranceNumber'];

            $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
            $tp_insurer_address = keysToLower($tp_insurer_address);
        }

        $rto_code = explode('-', $requestData->rto_code);

        $rto_data = DB::table('tata_aig_v2_rto_master')
            //->where('txt_rto_code', 'like', '%' . $rto_code[0] . $rto_code[1] . '%')
            ->where('txt_rto_code', str_replace('-', '',RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
            ->first();

        //Special registration number
        $special_regno = "No";
        if (isset($proposal->vehicale_registration_number) && $proposal->vehicale_registration_number != null) {
            $pattern = '/^[A-Z]{2}-[0-9]{2}--[0-9]+$/';
            if (preg_match($pattern, $proposal->vehicale_registration_number)) {
                $special_regno = "Yes";
            } else {
                $special_regno = "No";
            }
        }

            $special_rto_data = TataAigV2SpecialRegNo::where('rto', str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code, true)))
                ->exists();
            if ($special_rto_data) {
                $special_regno = "Yes";
            }
        
        if (sizeof($vehicle_register_no) == 3) {
            $special_regno = "Yes";
            $number = $vehicle_register_no[2];
            $reg_no = str_split($number, strlen($number)/2);
            $vehicle_register_no[2] = $reg_no[0];
            $vehicle_register_no[3] = $reg_no[1];
        }
        if (config('constants.IcConstants.tata_aig_v2.NO_ANTITHEFT') == 'Y') {
            $is_anti_theft = false;
        }

        if (config('constants.IcConstants.tata_aig_v2.NO_NCB_PROTECTION') == 'Y') {
            $applicableAddon['NCBProtectionCover'] = 'No';
        }
        if (in_array(strtoupper($mmv->txt_segment), ['MINI', 'COMPACT', 'MPS SUV', 'MPV SUV', 'MID SIZE'])) {
            $engineProtectOption = "WITH DEDUCTIBLE";
        } else {
            $engineProtectOption = 'WITHOUT DEDUCTIBLE';
        }

        $prev_pol_type = "";
        if ($is_new) {
            $prev_pol_type = "";
        } elseif ($noPrevPolicy) {
            $prev_pol_type = "";
        } elseif ((in_array($requestData->previous_policy_type, ['Comprehensive']))) {
            $prev_pol_type = "Package (1 year OD + 1 Year TP)";
        } else {
            $prev_pol_type = "Standalone TP (1 year TP)";
        }
        $vehicle_sub_class = (policyProductType($productData->policy_id)->parent_id == 8) ? ($mmv->num_vehicle_sub_class == '103' ? 'C2 PCV 4 Wheeler Exceeding 6 passenger' : 'C1A PCV 4 wheeler not exceeding 6 passengers') : $mmv->vehicle_sub_class;

        $prevPolyStartDate = '';
        if (in_array($requestData->previous_policy_type, ['Comprehensive', 'Third-party', 'Own-damage'])) {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
        } 
    
        if ($requestData->prev_short_term == "1") {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subMonth(3)->addDay(1)->format('Y-m-d');
        } 
        $quoteRequest = [
            "source" => "P",

            "sub_product_code" => "3188",
            "sub_product_name" => "PCV",
            "vehicle_usage_type" => "Corporate Taxi",
            "vehicle_usage_type_code" => "127",

            "q_producer_code" => config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_PRODUCER_CODE'),
            "q_producer_email" => config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_PRODUCER_EMAIL'),

            "sp_code" => "",
            "proposer_type" => $customer_type,

            "email" => $proposal->email,
            "mobile_no" => $proposal->mobile_number,

            "product_id" => config("constants.IcConstants.tata_aig.PCV_PRODUCT_ID"),
            "proposer_pincode" => (string)($proposal->pincode),
            "business_type" => ($requestData->ownership_changed == 'Y' ? "Used Vehicle" : "Roll Over"), // #33380
            "business_type_no" => ($is_breakin_short_term ? '04' : '03'),
            "dor_first" => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),
            "dor" => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),

            "pol_plan_variant" => ($is_liability ? 'Standalone TP' : "PackagePolicy" ),
            // "pol_plan" => "Package (1 year OD + 1 year TP)",
            "pol_start_date" => Carbon::parse($policy_start_date)->format('Y-m-d'),
            "pol_end_date" => Carbon::parse($policy_end_date)->format('Y-m-d'),

            "prev_pol_end_date" => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d')),
            "prev_pol_start_date" => (($is_new || $noPrevPolicy) ? '' : $prevPolyStartDate),
            "man_year" => (int)(Carbon::parse($requestData->vehicle_register_date)->format('Y')),
            "special_regno" => $special_regno,
            "regno_1"                       => $vehicle_register_no[0] ?? "",
            "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")), // (string)($vehicle_register_no[1] ?? ""), (string)($vehicle_register_no[1] ?? ""),
            "regno_3"                       => $vehicle_register_no[2] ?? "",
            "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),

            "no_past_pol" => "",
            "prev_pol_type" => $prev_pol_type,

            'claim_last'                    => (($is_new || $noPrevPolicy) ? 'No' : (($requestData->is_claim == 'N' || $is_liability) ? 'No' : 'Yes')),
            'claim_last_amount'             => "",
            'claim_last_count'              => "",

            "pre_pol_ncb" => (($is_new || $noPrevPolicy || $requestData->is_claim == 'Y' ) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
            "pre_pol_protect_ncb" => "NA",

            "vehicle_class" => $mmv->num_vehicle_class,
            "vehicle_sub_class" => $vehicle_sub_class,//$mmv->num_vehicle_sub_class,
            "vehicle_sub_class_no" => $mmv->num_vehicle_sub_class,
            "vehicle_make" => $mmv->txt_manufacturername,
            "vehicle_model" => $mmv->txt_model_name,
            "vehicle_variant" => $mmv->txt_model_variant,
            "make_code" => (int)$mmv->num_manufacturercode,
            "model_code" => (int)$mmv->num_model_code,
            "variant_code" => (int)$mmv->num_model_variant_code,

            'place_reg'                     => $rto_data->txt_rtolocation_name,
            'place_reg_no'                  => $rto_data->txt_rtolocation_code,

            "gvw" => (int)((!empty($mmv->num_gross_vehicle_weight)) ? $mmv->num_gross_vehicle_weight : 0),
            "sub_body_type" => "null",
            "nature_goods" => "Non-Hazardous",

            "vehicle_carrying" => $mmv->num_carrying_capacity,
            "vehicle_built_type" => "",
            "vehicle_color" => "",
            "vehicle_city" => "",
            "vehicle_mobility" => "",
            "route_from" => "",
            "route_to" => "",
            "vehicle_permit" => "",

            "body_idv" => 0,
            "chassis_idv" => 0,

            'cng_lpg_cover'                 => (string)($externalCNGKIT),
            'cng_lpg_si'                    => ($is_liability ? '0' : (string)($externalCNGKITSI)),

            "electrical_cover" => $Electricalaccess,
            "non_electrical_cover" => $NonElectricalaccess,
            'electrical_si'                 => (string)($ElectricalaccessSI),
            'non_electrical_si'             => (string)($NonElectricalaccessSI),
            "electrical_desc" => "",
            "non_electrical_desc" => "",

            "add_vehicle_towing" => "No",
            "add_vehicle_towing_amount" => "",
            "trailer_attach" => "No",
            "trailer_no" => "",
            "trailer_idv1" => 0,
            "trailer_idv2" => "",
            "trailer_idv" => "",
            "total_idv" => "",
            "system_discount" => "",
            "uw_loading" => "",
            "uw_discount" => "",
            "uw_remarks" => "",
            "antitheft_cover" => $is_anti_theft ? 'Yes' : 'No',
            "voluntary_amount" => (string)($voluntary_excess_amt),
            "tppd_discount" => $is_tppd ? 'Yes' : 'No',
            "own_premises" => "No",
            "vehicle_blind" => "No",
            "confined_ownsite" => "No",

            "ncb_protection" => $applicableAddon['NCBProtectionCover'],
            "ncb_no_of_claims" => 0,

            'tyre_secure'                   => $applicableAddon['TyreSecure'],
            'tyre_secure_options'           => $applicableAddon['TyreSecure'] == 'Yes' ? 'REPLACEMENT BASIS' : '', // 'DEPRECIATION BASIS'

            'engine_secure'                 => $applicableAddon['EngineSecure'],
            'engine_secure_options'         => "", // $is_liability ? '' : $engineProtectOption,

            'dep_reimburse'                 => $applicableAddon['DepreciationReimbursement'],
            'dep_reimburse_claims'          => $NoOfClaimsDepreciation,

            "add_towing" => "No",
            "add_towing_amount" => "",

            "return_invoice" => $applicableAddon['ReturnToInvoice'],
            'consumbale_expense'            => $applicableAddon['ConsumablesExpenses'],
            "rsa" => $applicableAddon['RoadsideAssistance'],
            "loss_equipments" => "No",
            "key_replace" =>  $applicableAddon['KeyReplacement'],
            "key_replace_si" => "",
            "emergency_expense" => $applicableAddon['EmergTrnsprtAndHotelExpense'],
            "emergency_expense_si" => "",
            "aoa_si" => "",
            "aoy_si" => "",
            "emergency_medical" => "No",
            "emergency_medical_si" => "",
            "personal_loss" => $applicableAddon['LossOfPersonalBelongings'],
            "personal_loss_si" => "",
            "add_tppd" => "No",
            "add_tppd_si" => "",
            "loss_income" => "No",
            "loss_income_days" => "",
            "emi_protector" => "No",
            "emi_protector_si" => "",
            "rim_guard" => "No",
            "rim_guard_si" => "",
            "add_transport" => "No",
            "add_transport_si" => "",
            "repair_glass" => $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'],

            "ll_paid" => $is_ll_paid,
            "ll_pass" => "No",
            "ll_pass_no" => "",
            "ll_pass_fare" => "No",
            "ll_pass_fare_no" => "",
            "ll_pass_non_fare" => "No",
            "ll_pass_non_fare_no" => "",
            "ll_pass_non_fare_emp" => "No",
            "ll_pass_non_fare_emp_no" => "",
            "ll_operator" => "No",
            "ll_operator_no" => "",
            "ll_wc" => "No",
            "ll_wc_no" => "",
            "ll_pass_accident" => "No",
            "ll_pass_accident_no" => "",

            "load_fibre" => "No",
            "load_tuition" => "No",
            "cover_lamps" => "No",
            "load_imported" => "No",
            "commerical_private" => "No",
            "liability_theft" => "",
            "theft_conversion" => "No",
            "idemnity_hirer_36" => "No",
            "hired_hirer_35" => "No",
            "overturning_load" => "No",
            "imposed_excess" => "",
            "idemnity_hirer_44" => "No",
            "idemnity_hirer_45" => "No",

            "geography_extension" => $is_Geographical,
            "geography_extension_bang" => in_array('Bangladesh', $countries) ? "Yes" : "No",
            "geography_extension_bhutan" => in_array('Bhutan', $countries) ? "Yes" : "No",
            "geography_extension_lanka" => in_array('Sri Lanka', $countries) ? "Yes" : "No",
            "geography_extension_maldives" => in_array('Nepal', $countries) ? "Yes" : "No",
            "geography_extension_nepal" => in_array('Maldives', $countries) ? "Yes" : "No",
            "geography_extension_pak" => in_array('Pakistan', $countries) ? "Yes" : "No",

            "prev_engine" => "No",
            "prev_rti" => "No",
            "prev_tyre" => "No",
            "prev_consumable" => $applicableAddon['ConsumablesExpenses'],

            "add_pa" => "No",
            "add_pa_si" => "",
            "add_pa_unnamed" => "No",
            "add_pa_unnamed_si" => "",
            "cvplying_cluster" => "",
            'pa_owner' => (($is_individual && !$is_od) ? ($cpa_cover ? 'true' : 'false') : 'false'),
            'pa_owner_declaration' => $driver_declaration,
            'pa_owner_tenure' => $pa_owner_tenure,

            "pa_unnamed" => "No",
            "pa_unnamed_no" => "",
            "pa_unnamed_si" => "",

            // "pa_paid" => "Yes",
            "pa_paid_no" => 0,
            "pa_paid_si" => 0,
            "fleetCode" => config('IC.TATA_AIG.V2.PCV.FLEET_CODE'),//"2182129",
            "fleetName" => config('IC.TATA_AIG.V2.PCV.FLEET_NAME'),//"PCV PARTNER FLEET",
            "fleetOpted" => true,
            "optionForCalculation" => "Pro Rata",
            "quote_id" => "",
           // "carriedOutBy"=> $is_breakin ? "Yes" : "No",
            "__finalize" => "1"
        ];
        if ($breakin ) {
            $quoteRequest['carriedOutBy'] = "Yes";
        }
        $hazardousType = $proposal_additional_details['vehicle']['hazardousType'] ?? '';
        if ($hazardousType == "Hazardous") {

            return [
                "status" => false,
                "message" => "Vehicle is Hazardous to Nature",
            ];
        }

        if ($is_posp == "Y") {
            $quoteRequest['is_posp'] = $is_posp;
            $quoteRequest['sol_id'] = $sol_id;
            $quoteRequest['q_agent_pan'] = $sol_id;
            $quoteRequest['q_office_location'] = $q_office_location;
        }

        if ($requestData->previous_policy_type == 'Not sure') {
            $quoteRequest['no_past_policy'] = 'Yes';
        } else {
            $quoteRequest['no_past_policy'] = 'No';
        }


        if ($applicableAddon['NCBProtectionCover'] == 'Yes') {
            $quoteRequest['ncb_no_of_claims'] = 1;
        }
        //checking last addons
        $PreviousPolicy_IsZeroDept_Cover
            = $PreviousPolicy_IsConsumable_Cover
            = $PreviousPolicy_IsReturnToInvoice_Cover
            = $PreviousPolicy_IsTyre_Cover
            = $PreviousPolicy_IsEngine_Cover
            = $PreviousPolicy_IsLpgCng_Cover
            = $is_breakin_case =  true;

        if (!empty($proposal->previous_policy_addons_list)) {

            $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list)
                ? $proposal->previous_policy_addons_list
                : json_decode($proposal->previous_policy_addons_list);

            foreach ($previous_policy_addons_list as $key => $value) {
                if ($key == 'zeroDepreciation' && $value) {
                    $PreviousPolicy_IsZeroDept_Cover = true;
                }
            }
        }
        $quoteRequest['prev_cnglpg'] = 'No';
        if (!$is_new && !$noPrevPolicy) {
            if ($is_lpg_cng) {
                $quoteRequest['prev_cnglpg'] = 'Yes';
                if (!$PreviousPolicy_IsLpgCng_Cover && !$is_liability) {
                    $is_breakin_case =  true;
                    $quoteRequest['prev_cnglpg'] = 'No';
                }
            }

            if ($is_liability) {
                $quoteRequest['prev_cnglpg'] = 'No';
            }

            $quoteRequest['prev_dep'] = 'No';
            if ($applicableAddon['DepreciationReimbursement'] == 'Yes') {
                $quoteRequest['prev_dep'] = 'Yes';
                if (!$PreviousPolicy_IsZeroDept_Cover) {
                    $quoteRequest['prev_dep'] = 'No';
                    $is_breakin_case =  true;
                }
            }
        }

        if (!$is_od) {
            if ($PAforUnnamedPassenger == 'Yes') {
                $quoteRequest['pa_unnamed'] = $PAforUnnamedPassenger;
                $quoteRequest['pa_unnamed_csi'] = '';
                $quoteRequest['pa_unnamed_no'] = (string)($mmv->num_seating_capacity);
                $quoteRequest['pa_unnamed_si'] = (string)$PAforUnnamedPassengerSI;
            }
            if ($llpaidDriver == 'Yes') {
                $quoteRequest['ll_paid'] = $llpaidDriver;
                $quoteRequest['ll_paid_no'] = $LLNumberDriver +  $LLNumberConductor + $LLNumberCleaner;
            }
            if ($PAforaddionaldPaidDriver == 'Yes') {
                $quoteRequest['pa_paid'] = $PAforaddionaldPaidDriver;
                $quoteRequest['pa_paid_no'] = 1;
                $quoteRequest['pa_paid_si'] = $PAforaddionaldPaidDriverSI;
            }
        }

        if ($is_od) {
            $quoteRequest['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
            $quoteRequest['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');

            $quoteRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
            $quoteRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
        }

        // $quoteRequest = self::addAdditionalKeys($quoteRequest);

        $quoteRequest['vehicle_idv'] = (string)($idv);
        $quoteRequest['__finalize'] = '1';
        $quoteRequest["chassis_idv"] = ($idv);

        $tokenResponse = TataAigV2Helper::getToken($enquiryId,$productData, "proposal");
        if(!$tokenResponse['status']) {
            return $tokenResponse;
        }
        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig_v2',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium calculation - Proposal',
            'transaction_type'  => "proposal",
            'headers' => [
                'Content-Type'   => "application/json",
                "x-api-key" => $configCreds->api_key,
                'Authorization' =>  $tokenResponse["token"],
            ]

        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
        $quoteResponse = $get_response['response'];

       

        if (!($quoteResponse && $quoteResponse != '' && $quoteResponse != null)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        $quoteResponse = json_decode($quoteResponse, true);
        ksort($quoteRequest);
        if (empty($quoteResponse)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        if (!isset($quoteResponse['status'])) {
            return [
                'status'    => false,
                'msg'       => $quoteResponse['message_txt'] ?? ($quoteResponse['message'] ?? 'Insurer Not Reachable'),
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        if ($quoteResponse['status'] != 200) {
            if (!isset($quoteResponse['message_txt'])) {
                return [
                    'status'    => false,
                    'msg'       => 'Insurer Not Reachable',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $quoteRequest,
                    'stage'     => 'quote'
                ];
            }
            return [
                'status'    => false,
                'msg'       => $quoteResponse['message_txt'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }

        $premiumWebServiceId = $get_response['webservice_id'];
        $quoteResponse2 = $quoteResponse;

        $quoteResponse = $quoteResponse['data'][0]['data'];
        $pol = $quoteResponse2['data'][0]['pol_dlts'];


        if ($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true') {
            return [
                'status' => false,
                'message' => $quoteResponse2['data'][0]['pol_dlts']['refferalMsg'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'product_identifier' => $masterProduct->product_identifier,
                'quoteResponse' => $quoteResponse
            ];
        }

        //check inspection tag 
        $inspectionFlag = $pol["inspectionFlag"] ?? "";

        // pass idv
        $max_idv    = $quoteResponse['max_idv_body'];
        $min_idv    = $quoteResponse['min_idv_body'];

        $policy_id = $quoteResponse['policy_id'];
        $quote_id = $quoteResponse['quote_id'];
        $quote_no = $quoteResponse['quote_no'];
        $proposal_id = $quoteResponse['proposal_id'];
        $product_id = $quoteResponse['product_id'];



        $totalOdPremium = $quoteResponse['premium_break_up']['total_od_premium'];
        $totalAddons    = $quoteResponse['premium_break_up']['total_addOns'];
        $totalTpPremium = $quoteResponse['premium_break_up']['total_tp_premium'];

        $premium_without_gst = $quoteResponse['premium_break_up']['premium_value']; //net premium as without gst value use final premium
        $total_payable   = $quoteResponse['premium_break_up']['net_premium'];
        $basic_od       = $totalOdPremium['od']['basic_od'];
        $total_od       = $totalOdPremium['total_od'];
        $non_electrical = $totalOdPremium['od']['non_electrical_prem'];
        $electrical     = $totalOdPremium['od']['electrical_prem'];
        $lpg_cng_od     = $totalOdPremium['od']['cng_lpg_od_prem'];



        $basic_tp       = $totalTpPremium['basic_tp'];
        $total_tp       = $totalTpPremium['total_tp'];
        $pa_unnamed     = $totalTpPremium['pa_paid_drive_prem'];
        $ll_paid        = $totalTpPremium['ll_paid_drive_prem'];
        $lpg_cng_tp     = $totalTpPremium['cng_lpg_tp_prem'];

        $pa_paid        = (int)(isset($quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem']) ? $quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem'] : 0);

        $tp_gio = (float)(isset($totalTpPremium['geography_extension_tp_prem']) ? $totalTpPremium['geography_extension_tp_prem'] : 0);
        $od_gio = (float)(isset($totalOdPremium['od']['geography_extension_od_prem']) ? $totalOdPremium['od']['geography_extension_od_prem'] : 0);
        $final_od_premium = $basic_od + $non_electrical + $electrical + $lpg_cng_od + $od_gio;
        $final_tp_premium = $basic_tp + $pa_unnamed + $ll_paid + $lpg_cng_tp + $pa_paid + $tp_gio;
        $pa_owner       = $totalTpPremium['pa_paid_drive_prem'];
        $tppd_discount  = $pol['tppd_prem'];


        $anti_theft_amount       = 0; #$totalOdPremium['loading_od']['theft_conversion_prem'];# ['discount_od']['atd_disc_prem'];
        $automoble_amount       = 0; #$totalOdPremium;# ['discount_od']['aam_disc_prem'];
        $voluntary_deductible   = 0; #$totalOdPremium;# ['discount_od']['vd_disc_prem'];
        $ncb_discount_amount    = 0; #$totalOdPremium;# ['discount_od']['ncb_prem'];

        $final_total_discount = $ncb_discount_amount + $anti_theft_amount + $automoble_amount + $voluntary_deductible;



        $zero_dep_amount            = $totalAddons['dep_reimburse_prem'];
        $rsa_amount                 = $totalAddons['rsa_prem'];
        $ncb_protect_amount         = $totalAddons['ncb_protection_prem'];
        $engine_seccure_amount      = $totalAddons['engine_secure_prem'];
        $tyre_secure_amount         = $totalAddons['tyre_secure_prem'];
        $rti_amount                 = $totalAddons['return_invoice_prem'];
        $counsumable_amount         = $totalAddons['consumbale_expense_prem'];
        $key_replacment_amount      = $totalAddons['key_replace_prem'];
        $personal_belongings_amount = $totalAddons['personal_loss_prem'];

        // $emergency_expense_amount   = $totalAddons['emergency_expense_prem'];
        $repair_glass_prem          = $totalAddons['repair_glass_prem'];

        $final_addon_amount         = $totalAddons['total_addon'];

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

        $occupation = $is_individual ? $proposal_additional_details['owner']['occupation'] : '';

        $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

        if ($proposal_additional_details['vehicle']['isVehicleFinance']) {
            $financerAgreementType = $proposal_additional_details['vehicle']['financerAgreementType'];
            $nameOfFinancer = $proposal_additional_details['vehicle']['nameOfFinancer'];
            $hypothecationCity = $proposal_additional_details['vehicle']['hypothecationCity'];
            if (isset($proposal_additional_details['vehicle']['financer_sel'][0]['name'])) {
                $nameOfFinancer = $proposal_additional_details['vehicle']['financer_sel'][0]['name'];
            }
        }

        $pucExpiry = $pucNo = '';
        if (isset($proposal_additional_details['vehicle']['pucExpiry'])) {
            $pucExpiry = Carbon::parse($proposal_additional_details['vehicle']['pucExpiry'])->format('Y-m-d');
        }

        if (isset($proposal_additional_details['vehicle']['pucNo'])) {
            $pucNo = $proposal_additional_details['vehicle']['pucNo'];
        }

        if (is_numeric($nameOfFinancer)) {
            $financeData   = DB::table("tata_aig_finance_master")
                ->where("code", $nameOfFinancer)
                ->first();
            if (!empty($financeData)) {
                $nameOfFinancer = $financeData->name;
            }
        }
        $first_name = '';
        $middle_name = '';
        $last_name = '';

        $nameArray = $is_individual ? (explode(' ', trim($proposal->first_name . ' ' . $proposal->last_name))) : explode(' ', trim($proposal->first_name));

        $first_name = $nameArray[0];

        // for TATA f_name and l_name should only contain 1 word and rest will be in m_name
        if (count($nameArray) > 2) {
            $last_name = end($nameArray);
            array_pop($nameArray);
            array_shift($nameArray);
            $middle_name = implode(' ', $nameArray);
        } else {
            $middle_name = '';
            if (env('APP_ENV') == 'local') {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '.');
            } else {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '');
            }
        }
        // dd($proposal);
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 55,
            'address_2_limit'   => 55,
            'address_3_limit'   => 55,
        ];
        $getAddress = getAddress($address_data);


        $inspection_type_self = ($is_breakin_short_term ? (($proposal->inspection_type ?? '') == 'Manual' ? 'No' : 'Yes') : 'No');
      #inception approved response check quote  
       $inspectionOverride = false;

       if (($pol["inspectionOverride"] == true) ?? '') { 
        $inspectionOverride = true ; 
       }

        $proposalRequest = [
            "proposer_salutation" => $insured_prefix,
            "proposer_fname" => $first_name,
            "proposer_mname" => $middle_name,
            "proposer_lname" => empty($last_name) ? '.' : $last_name,
            "proposer_email" => strtolower($proposal->email),
            "proposer_mobile" => $proposal->mobile_number,
            "proposer_gender" => $gender,
            "proposer_dob" => $is_individual ? Carbon::parse($proposal->dob)->format('d/m/Y') : '',
            "proposer_marital" => $is_individual ? $proposal->marital_status : '',
            "proposer_aadhaar" => "",
            "proposer_occupation" => $occupation,
            "proposer_pan" => (string)($proposal->pan_number),
            "proposer_gstin" => (string)($proposal->gst_number),
            'proposer_add1' => trim($getAddress['address_1']) ?? '',
            'proposer_add2' => trim($getAddress['address_2']) ?? '',
            'proposer_add3' => trim($getAddress['address_3']) ?? '',

            "customer_name" => $insured_prefix . " ". $first_name ." ".$last_name,
            "vehicle_chassis" => removeSpecialCharactersFromString($proposal->chassis_number),
            "vehicle_engine" => removeSpecialCharactersFromString($proposal->engine_number),

            'vehicle_puc_expiry' => $pucExpiry,
            'vehicle_puc' => $pucNo,
            'vehicle_puc_declaration' => true,
            //self inception
            "proposalInspectionOverride" => $inspectionOverride,

           'pre_insurer_name' => (($is_new || $noPrevPolicy) ? '' : ($proposal->insurance_company_name) ?? ""),
            'pre_insurer_no' => (($is_new || $noPrevPolicy) ? '' : $proposal->previous_policy_number),
            'pre_insurer_address' => '', //$is_new ? '' : $proposal_additional_details['prepolicy']['previousInsuranceCompany'],
            "pre_insurer_code" => (($is_new || $noPrevPolicy) ? '' : ($proposal->previous_insurance_company) ?? ""),

            'financier_type' => $financerAgreementType,
            'financier_name' => $nameOfFinancer,
            'financier_address' => $hypothecationCity,

            'nominee_name' => (($is_individual && !$is_od) ? ($proposal->nominee_name ?? '') : ''),
            'nominee_relation' => (($is_individual && !$is_od) ? ($proposal->nominee_relationship ?? '') : ''),
            'nominee_age' => (($is_individual && !$is_od) ? ($proposal->nominee_age ?? '') : '0'),

            "declaration" => "Yes",
            "upload_status" => "",

            'proposal_id' => $proposal_id,
            'product_id' => $product_id,
            'quote_no' => $quote_no,
           // "carriedOutBy"=> $is_breakin ? "Yes" : "No",
            "__finalize" => "1"
        ];
        if ($breakin ) {
            $proposalRequest['carriedOutBy'] = "Yes";
        }

        if ($is_posp == "Y") {
            $proposalRequest['is_posp'] = $is_posp;
            $proposalRequest['sol_id'] = $sol_id;
            $proposalRequest['q_office_location'] = $q_office_location;
        }
        if ($occupation == 'OTHER') {
            $proposalRequest['proposer_occupation_other'] = 'OTHER';
        }

        if ($is_od) {
            $proposalRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
            $proposalRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');

            $proposalRequest['ble_tp_type']             = 'Package';
            $proposalRequest['ble_tp_tenure']           = '3';
            $proposalRequest['ble_tp_no']               = $tp_policy_no;
            $proposalRequest['ble_tp_name']             = $tp_insured;
            $proposalRequest['ble_tp_start']   = Carbon::parse($tp_start_date)->format('Y-m-d');
            $proposalRequest['ble_tp_end']     = Carbon::parse($tp_end_date)->format('Y-m-d');

            $proposalRequest['ble_saod_prev_no']        = $proposal_additional_details['prepolicy']['previousPolicyNumber'];

            $proposalRequest['od_pre_insurer_name']     = '';
            $proposalRequest['od_pre_insurer_no']       = '';
            $proposalRequest['od_pre_insurer_address']  = '';
        }

        if((config('IC.TATA_AIG_V2.CAR.PROPRIETORSHIP.ENABLED') == 'Y') && $requestData->vehicle_owner_type == "C"){
            if(!empty($additionalDetailsData->owner->organizationType) && $additionalDetailsData->owner->organizationType == 'Proprietorship'){
                $proposalRequest['prop_flag'] = 'true';
                $proposalRequest['prop_name'] = $proposal->proposer_ckyc_details->related_person_name;
            }else{
                $proposalRequest['prop_flag'] = 'false';
                $proposalRequest['prop_name'] = '';
            }
        }

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'tata_aig_v2',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Proposal Submition - Proposal',
            'transaction_type'  => "proposal",
            'headers' => [
                'Content-Type'   => "application/json",
                "x-api-key" => $configCreds->api_key,
                'Authorization' =>  $tokenResponse["token"],
            ]

        ];
        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.cv.SHORT_TERM_END_POINT_URL_PROPOSAL'), $proposalRequest, 'tata_aig_v2', $additional_data);
        $proposalResponse = $get_response['response'];

        if (!($proposalResponse && $proposalResponse != '' && $proposalResponse != null)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        }
        $proposalResponse = json_decode($proposalResponse, true);


        if (empty($proposalResponse)) {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        }


        // {"message": "Endpoint request timed out"}

        if (!isset($proposalResponse['status']) || $proposalResponse['status'] != 200) {
            if (!isset($proposalResponse['message_txt'])) {
                return [
                    'status'    => false,
                    'msg'       => $proposalResponse['message'] ?? 'Insurer Not Reachable',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $proposalRequest,
                    'stage'     => 'proposal'
                ];
            }
            return [
                'status'    => false,
                'msg'       => $proposalResponse['message_txt'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        } else {

            $proposalResponse2  = $proposalResponse;
            // echo "<pre>";print_r([$proposalResponse, $proposalRequest]);echo "</pre>";die();
            $proposalResponse   = $proposalResponse['data'][0];

            $proposal->od_premium               = round($final_od_premium) - round($final_total_discount);
            $proposal->tp_premium               = round($final_tp_premium) - round($tppd_discount);
            $proposal->cpa_premium              = $pa_owner;
            $proposal->addon_premium            = $final_addon_amount;
            $proposal->ncb_discount             = $ncb_discount_amount;
            $proposal->total_discount           = $final_total_discount + $tppd_discount;
            $proposal->service_tax_amount       = $total_payable * 0.18;
            $proposal->total_premium            = $total_payable;

            $proposal->proposal_no              = $proposalResponse['proposal_no'];
            $proposal->final_payable_amount     = $proposalResponse['premium_value'];

            $proposal->policy_start_date        = Carbon::parse($policy_start_date)->format('d-m-Y');
            $proposal->policy_end_date          = Carbon::parse($policy_end_date)->format('d-m-Y');
            $proposal->tp_start_date            = Carbon::parse($tp_start_date)->format('d-m-Y');
            $proposal->tp_end_date              =  Carbon::parse($tp_end_date)->format('d-m-Y');
            $tata_aig_v2_data = [
                'quote_no'       => $proposalResponse['quote_id'],
                'proposal_no'    => $proposalResponse['proposal_no'],
                'proposal_id'    => $proposalResponse['proposal_id'],
                'payment_id'     => $proposalResponse['payment_id'],
                'document_id'    => '', #$proposalResponse['document_id'],
                'policy_id'      => $proposalResponse['policy_id'],
                'master_policy_id' => $productData->policy_id,
            ];

            $isBreakinInspectionRequired = $inspectionFlag == "Y" ? true : false;
            if ($isBreakinInspectionRequired && !(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_PCV_INSPECTION_ENABLED') == 'Y')) {
                return [
                    'status'    => false,
                    'msg'       => 'Inspection is not allowed',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $proposalRequest,
                    'Request'   => $proposalResponse,
                    'stage'     => 'proposal'
                ];
            } else if ($isBreakinInspectionRequired && empty($proposalResponse['ticket_number'])) {

                if (!empty($proposalResponse['ticket_desc'])) {
                    $prefix = 'Lead already exists with id ';
                    if (strpos($proposalResponse['ticket_desc'], $prefix) !== false) {
                        $ticketNumber = str_replace($prefix, '', $proposalResponse['ticket_desc']);
                        $proposalResponse['ticket_number'] = $ticketNumber;
                    }
                }
                if (empty($proposalResponse['ticket_number'])) {
                    return [
                        'status'    => false,
                        'msg'       => 'Inspection Ticket Number not generated. Kindly reach each out Tata AIG.',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'Request'   => $proposalRequest,
                        'Request'   => $proposalResponse,
                        'stage'     => 'proposal'
                    ];
                }
            }

            $proposal_additional_details['tata_aig_v2'] = $tata_aig_v2_data;
            if($any_breakin_case && $disable_breakin_ckyc != 'Y'){
                $proposal_additional_details['proposalSubmit'] = $proposalResponse;
                $proposal_additional_details['webserviceData'] = $get_response;
            }

            $proposal->additional_details = json_encode($proposal_additional_details);
            $proposal->additional_details_data = $proposal_additional_details;
            // $proposal->is_breakin_case = ($isBreakinInspectionRequired || ($is_breakin_case) ? 'Y' : 'N');
            $proposal->save();




            userProposal::where(['user_product_journey_id' => $proposal->user_product_journey_id, 'user_proposal_id' => $proposal->user_proposal_id])
                ->update([
                    'od_premium' => $total_od - $final_addon_amount,
                    'tp_premium' => $total_tp,
                    'ncb_discount' => $ncb_discount_amount,
                    'electrical_accessories' => $electrical,
                    'non_electrical_accessories' => $non_electrical
                ]);


            $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
            $data['ic_id'] = $productData->policy_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;

            TataAigPremiumDetailController::saveV2PremiumDetails($premiumWebServiceId);

            updateJourneyStage($data);
            if($is_breakin && empty($proposalResponse['payment_id']) && $disable_breakin_ckyc != 'Y'){
                self::createTataBreakindata($proposal, $proposalResponse['ticket_number']);
            }

            $finsall = new \App\Http\Controllers\Finsall\FinsallController();
            $finsall->checkFinsallAvailability('tata_aig', 'cv', $premium_type, $proposal);

            if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_ENABLED_TATA_AIG') == 'Y') {
                try {
                    $is_breakin_case = ($isBreakinInspectionRequired ? 'Y' : 'N');

                    // $validateCKYC = self::validateCKYC($proposal, $proposalResponse, $get_response, $is_breakin_case);

                    if (config('constants.IcConstants.tata_aig_v2.IS_NEW_CKYC_FLOW_ENABLED_FOR_TATA_AIG_V2') == 'Y') {
                        $webserviceData = $get_response;
                        $proposalSubmitResponse = $proposalResponse;

                        $validateCKYC = ckycVerifications(compact('proposal', 'proposalSubmitResponse', 'webserviceData', 'is_breakin_case'));
                        $validateCKYCJSON = $validateCKYC;
                        if ( ! $validateCKYC['status']) {
                            if($is_breakin){
                            UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                                        ->update([
                                        'is_breakin_case' => 'N',
                                        ]);
                                $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
                                $data['ic_id'] = $productData->policy_id;
                                $data['stage'] = STAGE_NAMES['PROPOSAL_DRAFTED'];
                                $data['proposal_id'] = $proposal->user_proposal_id;
                                updateJourneyStage($data);
                            }
                            return response()->json($validateCKYC);
                        }else{
                            if($is_breakin){
                            UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                                        ->update([
                                        'is_breakin_case' => 'Y',
                                        ]);
                                $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
                                $data['ic_id'] = $productData->policy_id;
                                $data['stage'] = STAGE_NAMES['INSPECTION_PENDING'];
                                $data['proposal_id'] = $proposal->user_proposal_id;
                                updateJourneyStage($data);        
                            }
                        }
                    } else {
                        $validateCKYC = self::validateCKYC($proposal, $proposalResponse, $get_response, $is_breakin_case);

                        $validateCKYCJSON = $validateCKYC->getOriginalContent();

                        if (!$validateCKYCJSON['status']) {
                            return $validateCKYC;
                        }
                    }

                    if ($isBreakinInspectionRequired || ($is_breakin_case == 'Y')) {
                        if (!empty($validateCKYCJSON['data']['otp_id'] ?? '')) {
                            $additionalDetailsData = $proposal->additional_details_data;

                            $additionalDetailsData['is_breakin_case'] = $is_breakin_case;
                            $additionalDetailsData['ticket_number'] = $proposalResponse['ticket_number'];

                            $new_proposal_data = [
                                'additional_details_data' => json_encode($additionalDetailsData)
                            ];
                            UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                                ->update($new_proposal_data);
                        } else {
                            self::createTataBreakindata($proposal, $proposalResponse['ticket_number']);
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
            //Handling CKYC Bypass for Breakin condition.
            if (config('constants.IS_CKYC_ENABLED_TATA_AIG') != 'Y') {
                if (($is_breakin && !$inspectionOverride && empty($proposalResponse['payment_id'])) || $isBreakinInspectionRequired) {
                    UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                        ->update([
                            'is_breakin_case' => 'Y',
                        ]);
                    $proposal->refresh();
                    self::createTataBreakindata($proposal, $proposalResponse['ticket_number'], $disable_breakin_ckyc);
                    $proposal->refresh();
                }
            }
            $finsall = new \App\Http\Controllers\Finsall\FinsallController();
            $finsall->checkFinsallAvailability('tata_aig', 'cv', $premium_type, $proposal);
            $submitProposalResponse = [
                'status' => true,
                'msg' => 'Proposal Submitted Successfully..!',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $proposal->user_product_journey_id,
                    'proposalNo' => $proposalResponse['proposal_no'],
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => $proposal->is_breakin_case,
                    'isBreakinCase' => $proposal->is_breakin_case,
                    'inspection_number' => (isset($proposalResponse['ticket_number']) ? $proposalResponse['ticket_number'] : '')
                ]
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
    public static function checkTataAigPCVMMV($productData, $version_id, $requestData)
    {
        $product_sub_type_id = $productData->product_sub_type_id;

        $parent_id = get_parent_code($productData->product_sub_type_id);

        $mmv = get_mmv_details($productData, $version_id, 'tata_aig_v2');
    
        if ($mmv["status"] == 1) {
            $mmv_data = $mmv["data"];
        } else {
            return [
                "premium_amount" => "0",
                "status" => false,
                "message" => $mmv["message"],
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == "") {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => "Vehicle Not Mapped",
            ]);
        } elseif ($mmv_data->ic_version_code == "DNE") {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" =>
                "Vehicle code does not exist with Insurance company",
            ]);
        }

        return (array)$mmv;
    }
}
