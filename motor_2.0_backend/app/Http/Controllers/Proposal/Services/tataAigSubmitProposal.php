<?php

namespace App\Http\Controllers\Proposal\Services;

use App\Http\Controllers\Proposal\Services\V2\PCV\tataAigSubmitProposals;

use Config;
use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\Proposal\Services\tataAigV2SubmitProposal;
use App\Http\Controllers\Proposal\Services\V2\PCV\tataAigSubmitPcvProposals;

include_once app_path() . "/Helpers/CvWebServiceHelper.php";
include_once app_path() . '/Helpers/CkycHelpers/TataAigCkycHelper.php';
include_once app_path() . '/Http/Controllers/Proposal/Services/V2/PCV/tataAigSubmitProposals.php';

class tataAigSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        
        $enquiryId      = customDecrypt($request["userProductJourneyId"]);
        $requestData    = getQuotation($enquiryId);
        $productData    = getProductDataByIc($request["policyId"]);

        // if( config('IC.TATA_AIG.V2.PCV.ENABLE') == 'Y' && in_array(policyProductType($productData->policy_id)->parent_id , [8]) ) {
        //     return tataAigSubmitProposals::submitPcv($proposal, $request);
        // }   

        if (policyProductType($productData->policy_id)->parent_id == 4) {
            
            return tataAigV2SubmitProposal::submit($proposal, $request);
        } elseif (config('TATA_AIG_V2_PCV_FLOW') == 'Y' && in_array(policyProductType($productData->policy_id)->parent_id, [6, 8])) {

            return tataAigV2SubmitProposal::submitPcv($proposal, $request);
        }
        
        if(empty($proposal->previous_insurance_company) && $requestData->previous_policy_expiry_date != "New"){
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => "Previous company details not found. Kindly resubmit the proposal.",
            ]);
        }

        $masterProduct  = MasterProduct::where("master_policy_id", $productData->policy_id)
            ->first();
        $premium_type   = DB::table("master_premium_type")
            ->where("id", $productData->premium_type_id)
            ->pluck("premium_type_code")
            ->first();
        $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
            ->first();

        $rto_code = $requestData->rto_code;  
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true);

        $rto_location = DB::table("tata_aig_vehicle_rto_location_master")
            ->where("txt_rto_location_code", $rto_code)
            ->first();

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);

        $is_individual  = $requestData->vehicle_owner_type == "I" ? true : false;
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_three_months    = (($premium_type == 'short_term_3') ? true : false);
        $is_six_months      = (($premium_type == 'short_term_6') ? true : false);

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        $idv = $quote_log_data->idv;

        $check_mmv = self::checkTataAigMMV($productData, $requestData->version_id);

        if(!$check_mmv['status'])
        {
            return $check_mmv;
        }

        $mmv = (object)$check_mmv['data'];

        $customer_type = ($is_individual) ? "Individual" : "organization";

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
            $tp_end_date        =  $policy_end_date;
            $vehicle_age = "0";
        }
        else
        {
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new DateTime($vehicleDate);
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

        $policy_start_date  = date('Ymd', strtotime($policy_start_date));
        $policy_end_date    = date('Ymd', strtotime($policy_end_date));
      

        $vehicle_register_no = explode("-", $proposal->vehicale_registration_number);

        $previousInsurerList = PreviousInsurerList::where([
            "company_alias" => "tata_aig",
            "name" => strtoupper($proposal->insurance_company_name),
        ])->first();

        // addon
        $masterProduct = MasterProduct::where(
            "master_policy_id",
            $productData->policy_id)
        ->first();

        $selected_addons = SelectedAddons::where(
            "user_product_journey_id",
            $enquiryId
        )->first();

        $addons = 
            $selected_addons->applicable_addons == null
            ? []
            : $selected_addons->applicable_addons;

        $accessories =
            $selected_addons->accessories == null
            ? []
            : $selected_addons->accessories;

        $additional_covers =
            $selected_addons->additional_covers == null
            ? []
            : $selected_addons->additional_covers;

        $discounts =
            $selected_addons->discounts == null
            ? []
            : $selected_addons->discounts;

        $PAforaddionaldPaidDriver
        = $PAforaddionaldPaidDriverSI 
        = $Electricalaccess
        = $ElectricalaccessSI
        = $externalCNGKIT
        = $PAforUnnamedPassenger
        = $PAforUnnamedPassengerSI
        = $PAforaddionaldPassenger
        = $PAforaddionaldPassengerSI
        = $externalCNGKITSI
        = $NonElectricalaccess
        = $NonElectricalaccessSI
        = $PAPaidDriverConductorCleaner
        = $PAPaidDriverConductorCleanerSI
        = $llpaidDriver
        = $llpaidDriverSI
        = $is_anti_theft
        = $is_tppd
        = $DepreciationReimbursement
        = $is_voluntary_access = "N";

        $NoOfClaimsDepreciation = '0';

        $tppd_amt = 0;
        $voluntary_excess_amt = "";
        $is_anti_theft_device_certified_by_arai = "false";

        foreach ($addons as $key => $value) {
            if (in_array('Road Side Assistance', $value)) {
                $RoadsideAssistance = "Y";
            }

            if (in_array('NCB Protection', $value)) {
                $NCBProtectionCover = "Y";
            }

            if (in_array('Return To Invoice', $value)) {
                $rti = 'Y';
            }

            if (in_array('Consumable', $value)) {
                $ConsumablesExpenses = 'Y';
            }
            if (in_array('Zero Depreciation', $value)) {
                $DepreciationReimbursement  = 'Y';
                $NoOfClaimsDepreciation     = '2';
            }
        }

        if($is_liability){
            $DepreciationReimbursement = 'N';
            $NoOfClaimsDepreciation = '0';
        }

        $zero_dep_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 24 days - 8 months - 5 year')) : date('d-m-Y');
        if (
            !$is_new
            &&
                strtotime($zero_dep_age_limit) > strtotime($requestData->vehicle_register_date)
        )
        {
            $DepreciationReimbursement = 'N';
            $NoOfClaimsDepreciation = '0';
        }
        foreach ($accessories as $key => $value)
        {
            if (in_array("Electrical Accessories", $value))
            {
                $Electricalaccess = "Y";
                $ElectricalaccessSI = $value["sumInsured"];
            }

            if (in_array("Non-Electrical Accessories", $value))
            {
                $NonElectricalaccess = "Y";
                $NonElectricalaccessSI = $value["sumInsured"];
            }

            if (in_array("External Bi-Fuel Kit CNG/LPG", $value))
            {
                $externalCNGKIT = "Y";
                $externalCNGKITSI = $value["sumInsured"];

                if ($mmv->txt_fuel != " External CNG" || $mmv->txt_fuel != " External LPG")
                {
                    $mmv->txt_fuel = "External CNG";
                    $mmv->txt_fuelcode = "5";
                }
            }

            if (in_array("PA To PaidDriver Conductor Cleaner", $value))
            {
                $PAPaidDriverConductorCleaner = "Y";
                $PAPaidDriverConductorCleanerSI = $value["sumInsured"];
            }
        }

        foreach ($additional_covers as $key => $value)
        {
            if (in_array("PA cover for additional paid driver", $value))
            {
                $PAforaddionaldPaidDriver = "Y";
                $PAforaddionaldPaidDriverSI = $value["sumInsured"];
            }

            if (in_array("Unnamed Passenger PA Cover", $value))
            {
                $PAforUnnamedPassenger = "Y";
                $PAforUnnamedPassengerSI = $value["sumInsured"];
            }

            if (in_array("LL paid driver", $value))
            {
                $llpaidDriver = "Y";
                $llpaidDriverSI = $value["sumInsured"];
            }
        }

        foreach ($discounts as $key => $discount)
        {
            if ($discount["name"] == "anti-theft device" && !$is_liability)
            {
                $is_anti_theft = "Y";
                $is_anti_theft_device_certified_by_arai = "true";
            }

            if ($discount["name"] == "voluntary_insurer_discounts" && isset($discount["sumInsured"]))
            {
                $is_voluntary_access = "Y";
                $voluntary_excess_amt = $discount["sumInsured"];
            }

            if ($discount["name"] == "TPPD Cover" && !$is_od)
            {
                $is_tppd = "Y";
                $tppd_amt = "9999";
            }
        }

        // cpa vehicle

        $proposal_addtional_details = json_decode($proposal->additional_details, true);

        // cpa vehicle
        $driver_declaration = "ODD01";
        if (isset($selected_addons->compulsory_personal_accident[0]["name"]))
        {
            $cpa_cover = "Y";
            $driver_declaration = "ODD01";
        }
        else
        {
            $cpa_cover = "N";
            if ($customer_type == "Individual")
            {
                if (isset($proposal_addtional_details["prepolicy"]["reason"]) && $proposal_addtional_details["prepolicy"]["reason"] == "I have another motor policy with PA owner driver cover in my name")
                {
                    $driver_declaration = "ODD03";
                }
                elseif (isset($proposal_addtional_details["prepolicy"]["reason"]) && $proposal_addtional_details["prepolicy"]["reason"] == "I have another PA policy with cover amount greater than INR 15 Lacs")
                {
                    $driver_declaration = "ODD04";
                }
                elseif (isset($proposal_addtional_details["prepolicy"]["reason"]) &&$proposal_addtional_details["prepolicy"]["reason"] == "I do not have a valid driving license.")
                {
                    $driver_declaration = "ODD02";
                }
                else
                {
                    $driver_declaration = "ODD01";
                }
            }
        }

        $is_pos = config("constants.motorConstant.IS_POS_ENABLED");

        $pos_aadhar = "";
        $pos_pan = "";
        $sol_id = config("constants.IcConstants.tata_aig.SOAL_ID");

        $pos_data = DB::table("cv_agent_mappings")
            ->where(
                "user_product_journey_id",
                $requestData->user_product_journey_id
            )
            ->where("seller_type", "P")
            ->first();

        if ($is_pos == "Y" && isset($pos_data->seller_type) && $pos_data->seller_type == "P")
        {
            $pos_aadhar = $pos_data->aadhar_no;
            $pos_pan = $pos_data->pan_no;
            $sol_id = $pos_data->pan_no;
        }
        else
        {
            $is_pos = "N";
        }

        // addon

        $vehicleBodyType = DB::table('tata_aig_body_type_master')
            ->where('vehiclebodytypecode', $mmv->bodytypecode)
            ->first();

        $subproduct_name = 'Taxi';

        $subproduct_code = self::getSubProductName($subproduct_name);

        $mmv->txt_segmentcode = self::getSegmentType($mmv->txt_segmenttype);

        $mmv->txt_fuelcode = self::getFuelCode($mmv->txt_fuel);

        $subclass_name = 'C1A PCV 4 Wheeler not exceeding 6 passengers';
        $subclass_code = '63';

        $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

        if($proposal_addtional_details['vehicle']['isVehicleFinance'])
        {
            $financerAgreementType = $proposal_addtional_details['vehicle']['financerAgreementType'];
            $nameOfFinancer = $proposal_addtional_details['vehicle']['nameOfFinancer'];
            $hypothecationCity = $proposal_addtional_details['vehicle']['hypothecationCity'];
            if(isset($proposal_addtional_details['vehicle']['financer_sel'][0]['name']))
            {
                $nameOfFinancer = $proposal_addtional_details['vehicle']['financer_sel'][0]['name'];
            }
        }

        $proposal_vehicle_details = $proposal_addtional_details['vehicle'];

        $input_array_info = [
            "quotation_no"      => "",

            "sol_id"            => $sol_id,
            "lead_id"           => "",
            "mobile_no"         => $proposal->mobile_number,
            "email_id"          => $proposal->email,
            "emp_email_id"      => "",
            "customer_type"     => $customer_type,

            "product_code"      => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID"),
            "product_name"      => "Commercial Vehicle",
            "subproduct_code"   => $subproduct_code,
            "subproduct_name"   => $subproduct_name,
            "subclass_code"     => $subclass_code,
            "subclass_name"     => $subclass_name,

            "btype_code"        => $is_new || $is_od ? "1" : "2",
            "btype_name"        => $is_new || $is_od ? "New Business" : 'Roll Over',
            "covertype_code"    => ($is_package || $is_three_months || $is_six_months) ? "1" : "2",
            "covertype_name"    => ($is_package || $is_three_months || $is_six_months) ? 'Package' : 'Liability',

            "risk_startdate"    => $policy_start_date,
            "risk_enddate"      => $policy_end_date,
            "purchase_date"     => Carbon::parse($vehicleDate)->format("Ymd"),
            'regi_date'         => Carbon::parse($requestData->vehicle_register_date)->format('Ymd'),
            "veh_age"           => $vehicle_age,
            "manf_year"         => explode("-", $requestData->manufacture_year)[1],

            "gvw"               => "5",
            "age"               => "",
            "miscdtype_code"    => "",
            "bodytype_id"       => $vehicleBodyType->vehiclebodytypecode ?? '',
            'bodytype_desc'     => $vehicleBodyType->vehiclebodytypedescription ?? '',
            'type_of_body'    => '',
            'veh_sub_body'    => '',

            "idv"               => $idv,
            "revised_idv"       => $idv,

            "segment_code"      => $mmv->txt_segmentcode,
            "segment_name"      => $mmv->txt_segmenttype,
            "cc"                => $mmv->cubiccapacity,
            "sc"                => $mmv->seatingcapacity,
            "make_code"         => $mmv->manufacturercode,
            "make_name"         => $mmv->manufacturer,
            "model_code"        => $mmv->num_parent_model_code,
            "model_name"        => $mmv->vehiclemodel,
            "variant_code"      => $mmv->vehiclemodelcode,
            "variant_name"      => $mmv->txt_variant,
            "model_parent_code" => $mmv->num_parent_model_code,
            "fuel_code"         => $mmv->txt_fuelcode,
            "fuel_name"         => $mmv->txt_fuel,

            "regno_1"           => $vehicle_register_no[0] ?? "",
            "regno_2"           => $vehicle_register_no[1] ?? "",
            "regno_3"           => $vehicle_register_no[2] ?? "",
            "regno_4"           => $vehicle_register_no[3] ?? "",

            "rto_loc_code"      => $rto_code,
            "rto_loc_name"      => $rto_location->txt_rto_location_desc,
            "rtolocationgrpcd"  => $rto_location->txt_rto_location_grp_cd,
            "rto_zone"          => $rto_location->txt_registration_zone,

            "rating_logic" => "Campaign", //"Campaign",
            "campaign_id" => "",
            "fleet_id" => "",
            "discount_perc" => "",
            "pp_covertype_code" => $is_new ? "" : ($requestData->previous_policy_type == "Comprehensive" ? "1" : "2"),
            "pp_covertype_name" => $is_new ? "" : ($requestData->previous_policy_type == "Comprehensive" ? "Package" : "Liability"),
            "pp_enddate" => $is_new ? "" : Carbon::parse($requestData->previous_policy_expiry_date)->format("Ymd"),
            "pp_claim_yn" => $is_new ? "" : $requestData->is_claim,
            "pp_prev_ncb" => $is_new ? "" : ($is_liability ? "0" : $requestData->previous_ncb),
            "pp_curr_ncb" => $is_new ? "" : ($is_liability ? "0" : $requestData->applicable_ncb),

            "ac_opted_in_pp" => "Y",
            "addon_plan_code" => "",
            "addon_choice_code" => "",
            "cust_name" => "",
            "ab_cust_id" => "",
            "ab_emp_id" => "",
            "usr_name" => "",
            "producer_code" => "",
            "pup_check" => $is_pos,
            "pos_panNo" => $pos_pan,
            "pos_aadharNo" => $pos_aadhar,
            "is_cust_JandK" => "NO",
            "cust_pincode" => $proposal->pincode,
            "cust_gstin" => $proposal->gst_number ?? '',
            "tenure" => "1",
            "uw_discount" => "",
            "Uw_DisDb" => "",
            "uw_load" => "",
            "uw_loading_discount" => "",
            "uw_loading_discount_flag" => "",
            "engine_no" => "",
            "chasis_no" => "",
            "driver_declaration" => $driver_declaration,
            // "tppolicytype" => $is_od ? "Comprehensive Package" : "",
            // "tppolicytenure" => $is_od ? "3" : "",

            'basis_of_rating'     => 'Underwriting Discount',
            'driver_nominee_age'     => '40',
            'driver_nominee_name'     => 'dsdgfhfdg',
            'driver_nominee_relation'     => 'OTHERS',
            'external_built'     => '',
            'goods_normally_carry'     => 'non-hazardous',
            'misclass_code'     => '',
            'misclass_name'     => 'Select Miscellaneous Vehicle',
            'odometer_reading'    => '1',
            'rating_zone'    => 'B',
            'towed_by'    => '',
            'trailer_idv'    => '',
            'trailer_under_tow'    => '',
            'veh_cng_lpg_insured'    => 'N',
            'veh_type'    => 'indigenous',
        ];

        if($is_three_months || $is_six_months)
        {
            $input_array_info['option_for_calc'] = 'Pro Rata';
            $input_array_info['pre_policy_start_date'] = (
                $is_new
                ? ''
                : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Ymd')
            );
        }

        // quick quote service input
        $input_array = [
            "functionality" => "validatequote",
            "quote_type" => "quick",
            "vehicle" => $input_array_info,
            "cover" => [
                "C1" => [
                    "opted" => $is_liability ? "N" : "Y",
                ],
                "C2" => [
                    "opted" => $is_od ? "N" : "Y",
                ],
                "C3" => [
                    "opted" => $is_individual && !$is_od ? $cpa_cover : "N",
                    "tenure" => $is_new
                        ? ($cpa_cover == "Y"
                            ? "1"
                            : "0")
                        : ($is_individual
                            ? ($cpa_cover == "Y"
                                ? "1"
                                : "0")
                            : "0"),
                ],
            ],
        ];
        // quick quote service input

        $additional_data = [
            "enquiryId" => $enquiryId,
            "headers" => [],
            "requestMethod" => "post",
            "requestType" => "json",
            "section" => $productData->product_sub_type_code,
            "method" => "Premium Calculation",
            "transaction_type" => "proposal",
            "productName" => $productData->product_name,
        ];

        $inputArray = [
            "QDATA" => json_encode($input_array),
            "SRC" => config("constants.IcConstants.tata_aig.SRC"),
            "T" => config("constants.IcConstants.tata_aig.TOKEN"),
            "productid" => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID"),
        ];

        $get_response = getWsData(
            config("constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE"),
            $inputArray,
            "tata_aig",
            $additional_data
        );
        $response = $get_response['response'];

        if (!$response)
        {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => "Insurer Not Reachable",
            ]);
        }

        $response = json_decode($response, true);

        if (empty($response) || !isset($response["data"]["status"])) {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => "Insurer Not Reachable",
            ]);
        }

        if ($response["data"]["status"] == "0")
        {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => $response["data"]["message"],
            ]);
        }

        // pass idv
        $input_array_info["idv"] = $idv;
        $input_array_info["revised_idv"] = $idv;
        // pass idv

        //full quote service input
        $input_array = [
            "functionality" => "validatequote",
            "quote_type" => "full",
            "vehicle" => $input_array_info,
            "cover" => [
                "C1" => [
                    "opted" => $is_liability ? "N" : "Y",
                ],
                "C2" => [
                    "opted" => $is_od ? "N" : "Y",
                ],
                "C3" => [
                    "opted" => $is_individual || !$is_od ? $cpa_cover : "N",
                    "tenure" => (($cpa_cover == "Y" && $is_individual) ? ($is_new ? '3' : '1') : '0')
                ],
                "C4" => [
                    "opted" => !$is_liability ? $Electricalaccess : "N",
                    "SI" => !$is_liability ? $ElectricalaccessSI : "0",
                ],
                "C5" => [
                    "opted" => !$is_liability ? $NonElectricalaccess : "N",
                    "SI" => !$is_liability ? $NonElectricalaccessSI : "0",
                ],
                "C6" => [
                    "opted" => "N",
                    "SI" => "",
                ],
                "C7" => [
                    "opted" => !$is_liability ? $externalCNGKIT : "N",
                    "SI" => !$is_liability ? $externalCNGKITSI : "0", //"10000"
                ],
                "C11" => [
                    "opted" => $is_anti_theft,
                ],
                "C12" => [
                    "opted" => $is_tppd,
                ],
                "C18" => [
                    "opted" => !$is_od ? $llpaidDriver : "N",
                    "persons" => !$is_od ? "1" : "0",
                ],
                'C21' => [
                    'opted' => 'N',
                    'persons' => '0',
                ],
                'C22' => [
                    'opted' => 'N',
                    'persons' => '0',
                ],
                'C23' => [
                    'opted' => 'N',
                    'persons' => '0',
                ],
                'C24' => [
                    'opted' => ($is_package ? 'N' : 'N'),
                ],
                'C25' => [
                    'opted' => 'N',
                    'persons' => '0',
                ],
                'C26' => [
                    'opted' => 'N',
                    'persons' => '0',
                ],
                'C29' => [
                    'opted' => (!$is_od ? $externalCNGKIT : 'N')
                ],
                "C35" => [
                    "opted" => $DepreciationReimbursement,
                    "no_of_claims" => $NoOfClaimsDepreciation,
                    "Deductibles" => "0"
                ],
                'C53' => [
                    'opted' => 'N',
                    'SI' => NULL,
                ]
            ],
        ];

        //   full quote resuest
        $inputArray = [
            "QDATA"     => json_encode($input_array),
            "SRC"       => config("constants.IcConstants.tata_aig.SRC"),
            "T"         => config("constants.IcConstants.tata_aig.TOKEN"),
            "productid" => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID"),
        ];

        $additional_data["method"] = "Premium Calculation - Full Quote";
        $get_response = getWsData(
            config("constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE"),
            $inputArray,
            "tata_aig",
            $additional_data
        );
        $response = $get_response['response'];

        if (!$response)
        {
            return camelCase([
                "premium_amount" => "0",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                "status" => false,
                "message" => "Insurer Not Reachable",
            ]);
        }

        $response = json_decode($response, true);

        $quote_response = $response;

        if ($response["data"]["status"] == "0")
        {
            return response()->json(
                [
                    "status" => true,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    "msg" => $response["data"]["message"],
                ],
                500
            );
        }

        if ($is_individual)
        {
            if ($proposal->gender == "M" || $proposal->gender == "Male" || $proposal->gender == "MALE")
            {
                $insured_prefix = "Mr";
            }
            else
            {
                if (($proposal->gender == "F" || $proposal->gender == "Female" || $proposal->gender == "FEMALE") && $proposal->marital_status == "Single")
                {
                    $insured_prefix = "Miss";
                }
                else
                {
                    $insured_prefix = "Mrs";
                }
            }
        }
        else
        {
            $insured_prefix = "M/S";
        }

        $insurer = DB::table("insurer_address")
            ->where("Insurer", $proposal->insurance_company_name)
            ->first();
        $insurer = keysToLower($insurer);

        if ($is_od)
        {
            $tp_insured         = $proposal_addtional_details["prepolicy"]["tpInsuranceCompany"];
            $tp_insurer_name    = $proposal_addtional_details["prepolicy"]["tpInsuranceCompanyName"];
            $tp_start_date      = $proposal_addtional_details["prepolicy"]["tpStartDate"];
            $tp_end_date        = $proposal_addtional_details["prepolicy"]["tpEndDate"];
            $tp_policy_no       = $proposal_addtional_details["prepolicy"]["tpInsuranceNumber"];

            $tp_insurer_address = DB::table("insurer_address")
                ->where("Insurer", $tp_insurer_name)
                ->first();
            $tp_insurer_address = keysToLower($tp_insurer_address);
        }

        // proposal site

        $last_name = $proposal->last_name;
        if($is_individual && ($proposal->last_name === null || $proposal->last_name == ''))
        {
            //$proposal->last_name = '.';
            $last_name = '.';
        }

        $firstName = ($is_individual ? trim($proposal->first_name) : '');
        $names = explode(' ', $firstName);
        $middleName = count($names) > 1 ? end($names) : '';
        if (count($names) > 1) {
            $firstName =  implode(' ', array_splice($names, 0, count($names) - 1));
        }

        $proposal_input = [
            "functionality" => "validateproposal",
            "quotation_no"  => $response["data"]["quotationdata"]["quotation_no"],
            "sol_id"        => $sol_id,
            "lead_id"       => "",
            "pol_sdate"     => $policy_start_date,
            "sp_name"       => "",
            "sp_license"    => "",
            "sp_place"      => "",
            "productcod"    => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID_PROPOSALCOD"),
            "customer"      => [
                "salutation"        => $insured_prefix,
                "client_type"       => $customer_type,
                "organization_name" => (!$is_individual ? trim($proposal->first_name) : ''),
                "first_name"        => $firstName,
                "middle_name"       => $middleName,
                "last_name"         => ($is_individual ? trim($last_name) : ''),
                "gender"            => $proposal->gender,
                "dob"               => Carbon::parse($proposal->dob)->format("Ymd"),
                "marital_status"    => $proposal->marital_status,
                "address_1"         => $proposal->address_line1,
                "address_2"         => $proposal->address_line2,
                "address_3"         => $proposal->address_line3,
                "address_4"         => "",
                "pincode"           => $proposal->pincode,
                "account_no"        => "",
                "cust_aadhaar"      => "",
                "state"             => "",
                "nationality"       => "",
                "occupation"        => $proposal->occupation,
                "reg_pincode"       => "",
                "reg_pincode"       => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? '' : $proposal_vehicle_details['carRegistrationPincode']),
                "reg_addr1"         => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? '' : $proposal_vehicle_details['carRegistrationAddress1']),
                "reg_addr2"         => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? '' : $proposal_vehicle_details['carRegistrationAddress2']),
                "reg_addr3"         => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? '' : $proposal_vehicle_details['carRegistrationAddress3']),
                "reg_State"         => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? '' : $proposal_vehicle_details['carRegistrationState']),
                "reg_district"      => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? '' : $proposal_vehicle_details['carRegistrationCity']),
                "reg_city"          => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? '' : $proposal_vehicle_details['carRegistrationCity']),
                "rsc"               => ($proposal_vehicle_details['isCarRegistrationAddressSame'] ? 'Y' : 'N'),
                "cust_pan"          => $proposal->pan_number ?? '',
            ],
            "vehicle"       => [
                "engine_no"         => $proposal->engine_number,
                "chassis_no"        => $proposal->chassis_number,
                "PucDecFlag"        => "Y",
            ],
            "prevpolicy"    => [
                "flag"              => $is_new ? "N" : "Y",
                "code"              => $previousInsurerList->code ?? "",
                "name"              => $previousInsurerList->name ?? "",
                "address1"          => $insurer->address_line_1 ?? "",
                "address2"          => $insurer->address_line_2 ?? "",
                "address3"          => "",
                "polno"             => $proposal->previous_policy_number ?? "",
                "pincode"           => $insurer->pin ?? "",
                "doc_name"          => "",
            ],
            "financier"     => [
                "type"              => $financerAgreementType,
                "name"              => $nameOfFinancer,
                "address"           => $hypothecationCity,
                "loanacno"          => "",
            ],
            "automobile"    => [
                "flag"          => "N",
                "number"            => "",
                "name"              => "",
                "expiry_date"       => "19700101",
            ],
            "nominee"       => [
                "name"              => $proposal->nominee_name,
                "age"               => $proposal->nominee_age ?? "18",
                "relation"          => $proposal->nominee_relationship,
            ],
            "driver"        => [
                "flag"              => "N",
                "fname"             => "",
                "lname"             => "",
                "gender"            => "",
                "age"               => "",
                "drivingexp"        => "",
                "marital_status"    => "",
            ],
            "inspection"    => [
                "flag"              => "N",
                "number"            => "",
                "date"              => "",
                "agency_name"       => "",
                "imagename_1"       => "",
                "imagename_2"       => "",
                "imagename_3"       => "",
            ],
            "bundpolicy"    => [
                "flag"              => "N",
            ],
        ];
        if ($is_od)
        {
            $proposal_input["bundpolicy"] = [
                "flag"          => "Y",
                "code"          => $tp_insured,
                "tp_polnum"     => $tp_policy_no,
                "name"          => $tp_insurer_name,
                "pincode"       => $tp_insurer_address->pin,
                "bp_no"         => $proposal->previous_policy_number,
                "address1"      => $tp_insurer_address->address_line_1,
                "address2"      => $tp_insurer_address->address_line_2,
                "address3"      => "",

                "bp_edate"      => "",
                "op_sdate"      => Carbon::parse(date("Y-m-d", strtotime($proposal->prev_policy_expiry_date . "-1 year +1 day")))->format("Ymd"),
                "op_edate"      => Carbon::parse($proposal->prev_policy_expiry_date)->format("Ymd"),

                "tp_pol_sdate"  => Carbon::parse($tp_start_date)->format("Ymd"),
                "tp_pol_edate"  => Carbon::parse($tp_end_date)->format("Ymd"),
                "cpap_sdate"    => "",
                "cpap_edate"    => "",
                "cpap_tenure"   => "",
            ];
        }

        $inputArray = [
            "PDATA"         => json_encode($proposal_input),
            "SRC"           => config("constants.IcConstants.tata_aig.SRC"),
            "T"             => config("constants.IcConstants.tata_aig.TOKEN"),
            "product_code"  => config("constants.IcConstants.tata_aig.cv.PRODUCT_ID"),
            "THANKYOU_URL"  => route("cv.payment-confirm", ["tata_aig"]),
        ];

        // additional data
        $additional_data["method"]              = "Proposal Submition";
        $additional_data["transaction_type"]    = "proposal";
        // additional data

        $get_response = getWsData(
            config("constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_PROPOSAL"),
            $inputArray,
            "tata_aig",
            $additional_data
        );
        $response = $get_response['response'];

        if (!$response)
        {
            return camelCase([
                "premium_amount" => "0",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                "status" => false,
                "message" => "Insurer Not Reachable",
            ]);
        }

        $response = json_decode($response, true);

        if ($response["data"]["status"] == "1")
        {
            $final_total_discount = (isset($quote_response["data"]["C15"]) ? round($quote_response["data"]["C15"]["premium"]) : 0)
                + (isset($quote_response["data"]["C11"]) ? round($quote_response["data"]["C11"]["premium"]) : 0)
                + (isset($quote_response["data"]["C12"]) ? round($quote_response["data"]["C12"]["premium"]) : 0);

            $proposal->proposal_no          = $response["data"]["proposalno"];
            $proposal->discount_percent     = (isset($quote_response["data"]["discount"]["rate"]) && $quote_response["data"]["discount"]["rate"] != '') ? round($quote_response["data"]["discount"]["rate"]) : 0;

            $proposal->policy_start_date    = Carbon::parse($policy_start_date)->format("d-m-Y");
            $proposal->policy_end_date      = Carbon::parse($policy_end_date)->format("d-m-Y");
            $proposal->tp_start_date        = Carbon::parse($tp_start_date)->format('d-m-Y');
            $proposal->tp_end_date          =  Carbon::parse($tp_end_date)->format('d-m-Y');

            $proposal->od_premium           = (isset($quote_response["data"]["C1"]) ? round($quote_response["data"]["TOTALOD"]) : 0) - $final_total_discount;
            $proposal->tp_premium           = (isset($quote_response["data"]["C2"]) ? round($quote_response["data"]["NETTP"]) : 0);
            $proposal->cpa_premium          = (isset($quote_response["data"]["C3"]) ? round($quote_response["data"]["C3"]["premium"]) : 0);
            $proposal->ncb_discount         = (isset($quote_response["data"]["C15"]) ? round($quote_response["data"]["C15"]["premium"]) : 0);
            $proposal->addon_premium        = (isset($quote_response["data"]["NETADDON"]) ? round($quote_response["data"]["NETADDON"]) : 0);
            $proposal->total_premium        = (isset($quote_response["data"]["NETPREM"]) ? round($quote_response["data"]["NETPREM"]) : 0);
            $proposal->service_tax_amount   = (isset($quote_response["data"]["TAX"]) ? round($quote_response["data"]["TAX"]["total_prem"]) : 0);
            $proposal->final_payable_amount = $response["data"]["premium"];
            $proposal->unique_proposal_id   = $proposal_input["quotation_no"];


            $proposal->save();

            $data["user_product_journey_id"]    = customDecrypt($request["userProductJourneyId"]);
            $data["ic_id"]                      = $productData->policy_id;
            $data["stage"]                      = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data["proposal_id"]                = $proposal->user_proposal_id;
            updateJourneyStage($data);

            if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_ENABLED_TATA_AIG') == 'Y') {
                if (config('constants.IcConstants.tata_aig_v2.IS_NEW_CKYC_FLOW_ENABLED_FOR_TATA_AIG_V2') == 'Y') {
                    $is_breakin_case = $proposal->is_breakin_case;
                    $webserviceData = $get_response;
                    $proposalSubmitResponse = $response['data'];

                    return ckycVerifications(compact('proposal', 'proposalSubmitResponse', 'webserviceData', 'is_breakin_case'));
                } else {
                    try {
                        return self::validateCKYC($proposal, $response['data'], $get_response);
                    } catch(\Exception $e) {
                        return response()->json([
                            'status' => false,
                            'message' => $e->getMessage(),
                            'dev_msg' => 'Line No. : ' . $e->getLine(),
                        ]);
                    }
                }
            }

            return response()->json([
                "status" => true,
                "msg" => "Proposal Submited Successfully..!",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'insured_prefix' => $insured_prefix,
                "data" => camelCase([
                    "proposal_no" => $response["data"]["proposalno"],
                    "proposal" => $response,
                ]),
            ]);
        }
        else
        {
            return response()->json(
                [
                    "status" => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    "msg" => $response["data"]["message"],
                    "message" => $response["data"]["message"],
                ],
                500);
        }
    }

    public static function checkTataAigMMV($productData, $version_id)
    {
        $product_sub_type_id = $productData->product_sub_type_id;

        $mmv = get_mmv_details($productData, $version_id, 'tata_aig');

        if ($mmv["status"] == 1)
        {
            $mmv_data = $mmv["data"];
        }
        else
        {
            return [
                "premium_amount" => "0",
                "status" => false,
                "message" => $mmv["message"],
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == "")
        {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => "Vehicle Not Mapped",
            ]);
        }
        elseif ($mmv_data->ic_version_code == "DNE")
        {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" =>
                    "Vehicle code does not exist with Insurance company",
            ]);
        }

        return (array)$mmv;
    }

    public static function getSubProductName($subproduct_name)
    {
        switch ($subproduct_name) {
            case 'Goods Carrying Vehicle':
                $subproduct_code = '50';
            break;

            case 'Misc and Special Types':
                $subproduct_code = '51';
            break;

            case 'Passenger Carrying Two Wheeler':
                $subproduct_code = '52';
            break;

            case 'Passenger Carrying Vehicle':
                $subproduct_code = '53';
            break;
            case 'Taxi':
                $subproduct_code = '54';
            break;

            default:
                $subproduct_code = '50';
            break;
        }

        return $subproduct_code;
    }

    public static function getSegmentType($segmenttype)
    {

        switch ($segmenttype) {
            case 'Mini':
                $txt_segmentcode = '1';
                break;
            case 'Compact':
                $txt_segmentcode = '2';
                break;
            case 'Mid Size':
                $txt_segmentcode = '3';
                break;
            case 'High End':
                $txt_segmentcode = '4';
                break;
            case 'MPV SUV':
                $txt_segmentcode = '5';
                break;
            default:
                $txt_segmentcode = '2';
                break;
        }

        return $txt_segmentcode;
    }

    public static function getFuelCode($txt_fuel)
    {
        switch ($txt_fuel) {
            case 'Petrol':
                $txt_fuelcode = '1';
                break;
            case 'Diesel':
                $txt_fuelcode = '2';
                break;
            case 'CNG':
                $txt_fuelcode = '3';
                break;
            case 'Battery':
                $txt_fuelcode = '4';
                break;
            case 'External CNG':
                $txt_fuelcode = '5';
                break;
            case 'External CNG':
                $txt_fuelcode = '6';
                break;
            case 'Electricity':
                $txt_fuelcode = '7';
                break;
            case 'Hydrogen':
                $txt_fuelcode = '8';
                break;
            case 'LPG':
                $txt_fuelcode = '9';
                break;
            default:
                $txt_fuelcode = '2';
                break;
        }
        return $txt_fuelcode;
    }

    public static function validateCKYC(UserProposal $proposalData, Array $proposalSubmitResponse, Array $webserviceData)
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
                'msg' => 'Proposal Submited Successfully..!',
                'webservice_id' => $webserviceData['webservice_id'],
                'table' => $webserviceData['table'],
                'data' => [
                    'verification_status' => 'Y',
                    'proposalId' => $proposalData->user_proposal_id,
                    'userProductJourneyId' => $proposalData->user_product_journey_id,
                    'proposalNo' => $proposalSubmitResponse['proposalno'],
                    'finalPayableAmount' => $proposalData->final_payable_amount,
                    'is_breakin' => $proposalData->is_breakin_case,
                    'isBreakinCase' => $proposalData->is_breakin_case,
                    'inspection_number' => (isset($proposalSubmitResponse['ticket_number']) ? $proposalSubmitResponse['ticket_number'] : ''),
                ],
            ]);
        } else {
            if(!empty($ckyc_response['data']['otp_id'] ?? '')) {
                return response()->json([
                    "status" => true,
                    "message" => "OTP Sent Successfully!",
                    "data" => [
                        "verification_status" => false,
                        "message" => "OTP Sent Successfully!",
                        'otp_id' => $ckyc_response['data']['otp_id']
                    ]
                ]);
            }
            return response()->json([
                'status' => false,
                'msg' => $ckyc_response['data']['message'] ?? 'Something went wrong while doing the CKYC. Please try again.',
            ]);
        }
    }
}

