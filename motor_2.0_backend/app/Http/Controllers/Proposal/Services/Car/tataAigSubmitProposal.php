<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use Config;
use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use App\Http\Controllers\Proposal\Services\Car\tataAigV2SubmitProposal as TATA_AIG_V2;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class tataAigSubmitProposal
{
  /**
   * @param $proposal
   * @param $request
   * @return \Illuminate\Http\JsonResponse
   * */
  public static function submit($proposal, $request)
  {
    if(config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED') == 'Y')
    {
      return TATA_AIG_V2::submit($proposal, $request);
    }

    $enquiryId   = customDecrypt($request['userProductJourneyId']);
    $requestData = getQuotation($enquiryId);
    $productData = getProductDataByIc($request['policyId']);

    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
      ->first();

    $premium_type = DB::table('master_premium_type')
      ->where('id', $productData->premium_type_id)
      ->pluck('premium_type_code')
      ->first();

    $is_package     = (($premium_type == 'comprehensive') ? true : false);
    $is_liability   = (($premium_type == 'third_party') ? true : false);
    $is_od          = (($premium_type == 'own_damage') ? true : false);
    $is_indivisual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover") ? false : true);

    $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
      ->first();
    $idv = $quote_log_data->idv;

    $mmv = get_mmv_details($productData, $requestData->version_id, 'tata_aig');
    if ($mmv['status'] == 1) {
      $mmv = $mmv['data'];
    } else {
      return  [
        'premium_amount' => 0,
        'status' => false,
        'message' => $mmv['message']
      ];
    }
    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
      return camelCase([
        'premium_amount' => 0,
        'status' => false,
        'message' => 'Vehicle Not Mapped',
      ]);
    } elseif ($mmv->ic_version_code == 'DNE') {
      return camelCase([
        'premium_amount' => 0,
        'status' => false,
        'message' => 'Vehicle code does not exist with Insurance company',
      ]);
    }

    $rto_code = $requestData->rto_code;

    $rto_location = DB::table('tata_aig_vehicle_rto_location_master')->where('txt_rto_location_code', $rto_code)->first();

    $customer_type = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

    $btype_code = $requestData->business_type == "rollover" ? "2" : "1";
    $btype_name = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";


    if($is_new){
      $policy_start_date  = date('Ymd', strtotime($requestData->vehicle_register_date));
      if($is_liability){
          $policy_start_date  = date('Ymd', strtotime($requestData->vehicle_register_date. '+ 1day'));
      }
      // $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 3 year'));
      if ($premium_type == 'comprehensive') {
        $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' + 1 year - 1 days'));
    } elseif ($premium_type == 'third_party') {
        $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 3 year'));
    }
    $tp_start_date      =  $policy_start_date;
    $tp_end_date        = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 3 year'));
      $vehicle_age        = '0';
    }
    else
    {
      $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
      $date1 = new DateTime($vehicleDate);
      $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
      $interval = $date1->diff($date2);
      $age = (($interval->y * 12) + $interval->m) + 1;
      $vehicle_age = $interval->y;

      $motor_manf_date = '01-' . $requestData->manufacture_year;

      $current_date = date('Y-m-d');
      $policy_start_date = date('Ymd', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

      if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
        $policy_start_date = date('Ymd');
      }

      $policy_end_date = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));
      $tp_start_date      =  $policy_start_date;
      $tp_end_date        = $policy_end_date;
    }

    $vehicle_register_no = explode('-', $proposal->vehicale_registration_number);
    $previousInsurerList = PreviousInsurerList::where([
      'company_alias' => 'tata_aig',
      'code' => $proposal->previous_insurance_company
    ])->first();

    // echo "<pre>";print_r([
    //   $proposal->previous_insurance_company,
    //   $previousInsurerList,
    //   $requestData
    // ]);echo "</pre>";die();


    // addon
    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
      ->first();

    $RepairOfGlasPlastcFibNRubrGlas = $DepreciationReimbursement = $NoOfClaimsDepreciation = $ConsumablesExpenses = $LossOfPersonalBelongings = $EngineSecure = $TyreSecure = $KeyReplacement = $RoadsideAssistance = $ReturnToInvoice = $NCBProtectionCover = $EmergTrnsprtAndHotelExpense = $ac_opted_in_pp = "N";
    switch ($masterProduct->product_identifier) {
      case 'silver':
        $RepairOfGlasPlastcFibNRubrGlas = 'Y';
        $DepreciationReimbursement = 'N';
        $NoOfClaimsDepreciation = '0';
        $ConsumablesExpenses = 'N';
        $LossOfPersonalBelongings = 'N';
        $EngineSecure = 'N';
        $TyreSecure = 'N';
        $KeyReplacement = 'N';
        $RoadsideAssistance = 'N';
        $ReturnToInvoice = 'N';
        $NCBProtectionCover = 'Y';
        $EmergTrnsprtAndHotelExpense = 'N';
        $ac_opted_in_pp = 'N';
        break;

      case 'gold':
        $RepairOfGlasPlastcFibNRubrGlas = 'Y';
        $DepreciationReimbursement = 'N';
        $NoOfClaimsDepreciation = '0';
        $ConsumablesExpenses = 'N';
        $LossOfPersonalBelongings = 'Y';
        $EngineSecure = 'N';
        $TyreSecure = 'N';
        $KeyReplacement = 'Y';
        $RoadsideAssistance = 'Y';
        $ReturnToInvoice = 'N';
        $NCBProtectionCover = 'Y';
        $EmergTrnsprtAndHotelExpense = 'Y';
        $ac_opted_in_pp = 'N';
        break;

      case 'pearl':
        $RepairOfGlasPlastcFibNRubrGlas = 'Y';
        $DepreciationReimbursement = 'Y';
        $NoOfClaimsDepreciation = '2';
        $ConsumablesExpenses = 'N';
        $LossOfPersonalBelongings = 'Y';
        $EngineSecure = 'N';
        $TyreSecure = 'N';
        $KeyReplacement = 'Y';
        $RoadsideAssistance = 'Y';
        $ReturnToInvoice = 'N';
        $NCBProtectionCover = 'Y';
        $EmergTrnsprtAndHotelExpense = 'Y';

        break;

      case 'pearl+':
        $RepairOfGlasPlastcFibNRubrGlas = 'Y';
        $DepreciationReimbursement = 'Y';
        $NoOfClaimsDepreciation = '2';
        $ConsumablesExpenses = 'Y';
        $LossOfPersonalBelongings = 'Y';
        $EngineSecure = 'Y';
        $TyreSecure = 'N';
        $KeyReplacement = 'Y';
        $RoadsideAssistance = 'Y';
        $ReturnToInvoice = 'N';
        $NCBProtectionCover = 'Y';
        $EmergTrnsprtAndHotelExpense = 'Y';

        break;

      case 'sapphire':
        $RepairOfGlasPlastcFibNRubrGlas = 'Y';
        $DepreciationReimbursement = 'Y';
        $NoOfClaimsDepreciation = '2';
        $ConsumablesExpenses = 'Y';
        $LossOfPersonalBelongings = 'Y';
        $EngineSecure = 'N';
        $TyreSecure = 'Y';
        $KeyReplacement = 'Y';
        $RoadsideAssistance = 'Y';
        $ReturnToInvoice = 'N';
        $NCBProtectionCover = 'Y';
        $EmergTrnsprtAndHotelExpense = 'Y';

        break;

      case 'sapphire+':
        $RepairOfGlasPlastcFibNRubrGlas = 'Y';
        $DepreciationReimbursement = 'Y';
        $NoOfClaimsDepreciation = '2';
        $ConsumablesExpenses = 'Y';
        $LossOfPersonalBelongings = 'Y';
        $EngineSecure = 'Y';
        $TyreSecure = 'Y';
        $KeyReplacement = 'Y';
        $RoadsideAssistance = 'Y';
        $ReturnToInvoice = 'N';
        $NCBProtectionCover = 'Y';
        $EmergTrnsprtAndHotelExpense = 'Y';

        break;

      case 'sapphire++':
        $RepairOfGlasPlastcFibNRubrGlas = 'Y';
        $DepreciationReimbursement = 'Y';
        $NoOfClaimsDepreciation = '2';
        $ConsumablesExpenses = 'Y';
        $LossOfPersonalBelongings = 'Y';
        $EngineSecure = 'Y';
        $TyreSecure = 'Y';
        $KeyReplacement = 'Y';
        $RoadsideAssistance = 'Y';
        $ReturnToInvoice = 'Y';
        $NCBProtectionCover = 'Y';
        $EmergTrnsprtAndHotelExpense = 'Y';
        break;
    }

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $PAforaddionaldPaidDriver = $PAforaddionaldPaidDriverSI = "N";
    $NCBProtectionCover = "N";
    foreach ($addons as $key => $value) {
      if (in_array('Road Side Assistance', $value)) {
        $RoadsideAssistance = "Y";
      }

      if (in_array('NCB Protection', $value)) {
        $NCBProtectionCover = "Y";
      }
    }
    $Electricalaccess = $ElectricalaccessSI = $externalCNGKIT = $PAforUnnamedPassenger = $PAforUnnamedPassengerSI = $PAforaddionaldPassenger = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccess = $NonElectricalaccessSI = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $llpaidDriver = $llpaidDriverSI = "N";

    $is_anti_theft = "N";
    $is_anti_theft_device_certified_by_arai = "false";
    $is_tppd = 'N';
    $tppd_amt = 0;
    $is_voluntary_access = 'N';
    $voluntary_excess_amt = '';

    foreach ($accessories as $key => $value) {
      if (in_array('Electrical Accessories', $value)) {
        $Electricalaccess = "Y";
        $ElectricalaccessSI = $value['sumInsured'];
      }

      if (in_array('Non-Electrical Accessories', $value)) {
        $NonElectricalaccess = "Y";
        $NonElectricalaccessSI = $value['sumInsured'];
      }

      if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
        $externalCNGKIT = "Y";
        $externalCNGKITSI = $value['sumInsured'];
        if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
          $mmv->txt_fuel = 'External CNG';
          $mmv->txt_fuelcode = '5';
        }
      }

      if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
        $PAPaidDriverConductorCleaner = "Y";
        $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
      }
    }

    foreach ($additional_covers as $key => $value) {
      if (in_array('PA cover for additional paid driver', $value)) {
        $PAforaddionaldPaidDriver = "Y";
        $PAforaddionaldPaidDriverSI = $value['sumInsured'];
      }

      if (in_array('Unnamed Passenger PA Cover', $value)) {
        $PAforUnnamedPassenger = "Y";
        $PAforUnnamedPassengerSI = $value['sumInsured'];
      }

      if (in_array('LL paid driver', $value)) {
        $llpaidDriver = "Y";
        $llpaidDriverSI = $value['sumInsured'];
      }
    }

    foreach ($discounts as $key => $discount) {
      if ($discount['name'] == 'anti-theft device' && !$is_liability) {
        $is_anti_theft = 'Y';
        $is_anti_theft_device_certified_by_arai = 'true';
      }

      if ($discount['name'] == 'voluntary_insurer_discounts' && isset($discount['sumInsured'])) {
        $is_voluntary_access = 'Y';
        $voluntary_excess_amt = $discount['sumInsured'];
      }

      if ($discount['name'] == 'TPPD Cover' && !$is_od) {
        $is_tppd = 'Y';
        $tppd_amt = '9999';
      }
    }



    // cpa vehicle

    $proposal_addtional_details = json_decode($proposal->additional_details, true);
    // cpa vehicle
    $driver_declaration  = "ODD01";
    if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
      $cpa_cover = "Y";
      $driver_declaration  = "ODD01";
    } else {
      $cpa_cover = "N";
      if ($customer_type == 'Individual') {
        if (isset($proposal_addtional_details['prepolicy']['reason']) && $proposal_addtional_details['prepolicy']['reason'] == "I have another motor policy with PA owner driver cover in my name") {
          $driver_declaration  = "ODD03";
        } elseif (isset($proposal_addtional_details['prepolicy']['reason']) && $proposal_addtional_details['prepolicy']['reason'] == "I have another PA policy with cover amount greater than INR 15 Lacs") {
          $driver_declaration  = "ODD04";
        } elseif (isset($proposal_addtional_details['prepolicy']['reason']) && $proposal_addtional_details['prepolicy']['reason'] == "I do not have a valid driving license.") {
          $driver_declaration  = "ODD02";
        } else {
          $driver_declaration  = "ODD01";
        }
      }
    }

    if($is_liability){
      $RepairOfGlasPlastcFibNRubrGlas = 'N';
      $DepreciationReimbursement = 'N';
      $NoOfClaimsDepreciation = '0';
      $ConsumablesExpenses = 'N';
      $LossOfPersonalBelongings = 'N';
      $EngineSecure = 'N';
      $TyreSecure = 'N';
      $KeyReplacement = 'N';
      $RoadsideAssistance = 'N';
      $ReturnToInvoice = 'N';
      $NCBProtectionCover = 'N';
      $EmergTrnsprtAndHotelExpense = 'N';
      $ac_opted_in_pp = 'N';
    }

    if($is_new){
      $NCBProtectionCover = 'N';
    }

    $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

    $pos_aadhar = '';
    $pos_pan    = '';
    $sol_id     = config('constants.IcConstants.tata_aig.SOAL_ID');

    $pos_data = DB::table('cv_agent_mappings')
      ->where('user_product_journey_id', $requestData->user_product_journey_id)
      ->where('seller_type','P')
      ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log_data->idv <= 5000000) {
      if($pos_data) {
        $pos_aadhar = $pos_data->aadhar_no;
        $pos_pan    = $pos_data->pan_no;
        $sol_id     = $pos_data->pan_no;
      }
    }
    else
    {
      $is_pos = 'N';
    }

    // addon

    $input_array_info =  [
      "quotation_no" => "",
      "segment_code" => $mmv->txt_segmentcode,
      "segment_name" => $mmv->txt_segmenttype,
      "cc" => $mmv->cubiccapacity,
      "sc" => $mmv->seatingcapacity,
      "sol_id" => $sol_id,//config('constants.IcConstants.tata_aig.SOAL_ID'),
      "lead_id" => "",
      "mobile_no" => $proposal->mobile_number,
      "email_id" => $proposal->email,
      "emp_email_id" => "",
      "customer_type" => $customer_type, //"Individual",
      "product_code" => config('constants.IcConstants.tata_aig.PRODUCT_ID'),
      "product_name" => "Private Car",
      "subproduct_code" => $mmv->vehicleclasscode,
      "subproduct_name" => "Private Car",
      "subclass_code" => "",
      "subclass_name" => "",
      "covertype_code" => (($is_package) ? '1' : (($is_liability) ? '2' : '3')),
      "covertype_name" => (($is_package) ? 'Package' : (($is_liability) ? 'Liability' : 'Standalone Own Damage')),
      "btype_code" => (($is_new || $is_od) ? '1' : '2'),
      "btype_name" => (($is_new || $is_od) ? 'New Business' : $btype_name),
      "risk_startdate" => $policy_start_date,
      "risk_enddate" => $policy_end_date,
      "purchase_date" => Carbon::parse($vehicleDate)->format('Ymd'),
      "veh_age" => $vehicle_age,
      "manf_year" => explode('-', $requestData->manufacture_year)[1],
      "make_code" => $mmv->manufacturercode,
      "make_name" => $mmv->manufacturer,
      "model_code" => $mmv->num_parent_model_code,
      "model_name" => $mmv->vehiclemodel,
      "variant_code" => $mmv->vehiclemodelcode,
      "variant_name" => $mmv->txt_varient,
      "model_parent_code" => $mmv->num_parent_model_code,
      "fuel_code" => $mmv->txt_fuelcode,
      "fuel_name" => $mmv->txt_fuel,
      "gvw" => "",
      "age" => "",
      "miscdtype_code" => "",
      "bodytype_id" => "34",
      "idv" => $idv,
      "revised_idv" => $idv,
      "regno_1" => $vehicle_register_no[0] ?? '',
      "regno_2" => $vehicle_register_no[1] ?? '',
      "regno_3" => $vehicle_register_no[2] ?? '',
      "regno_4" => $vehicle_register_no[3] ?? '',
      "rto_loc_code" => $requestData->rto_code,
      "rto_loc_name" => $rto_location->txt_rto_location_desc,
      "rtolocationgrpcd" => $rto_location->txt_rto_location_grp_cd,
      "rto_zone" => $rto_location->txt_registration_zone,
      "rating_logic" => "", //"Campaign",
      "campaign_id" => "",
      "fleet_id" => "",
      "discount_perc" => "",
      "pp_covertype_code" => ($is_new ? '' : (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? '1' : '2')),
      "pp_covertype_name" => ($is_new ? '' : (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Package' : 'Liability')),
      "pp_enddate"        => ($is_new ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Ymd')),
      "pp_claim_yn"       => ($is_new ? '' : $requestData->is_claim),
      "pp_prev_ncb"       => ($is_new ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
      "pp_curr_ncb"       => ($is_new ? '' : (($is_liability) ? '0' : $requestData->applicable_ncb)),
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
      "tenure" => (($is_new && $is_liability) ? '3' : '1'),
      "uw_discount" => "",
      "Uw_DisDb" => "",
      "uw_load" => "",
      "uw_loading_discount" => "",
      "uw_loading_discount_flag" => "",
      "engine_no" => "",
      "chasis_no" => "",
      "driver_declaration" => $driver_declaration,
      "tppolicytype"    => ($is_od ? 'Comprehensive Package' : ''),
      "tppolicytenure"  => ($is_od ? '3' : ''),
    ];


    // quick quote service input
    $input_array = [
      "functionality" => "validatequote",
      "quote_type" => "quick",
      "vehicle" => $input_array_info,
      "cover" => [
        "C1" => [
          "opted" => (($is_liability) ? 'N' : 'Y')
        ],
        "C2" => [
          "opted" => ($is_od ? 'N' : 'Y')
        ],
        "C3" => [
          "opted"   => (($is_indivisual && !$is_od) ? $cpa_cover : 'N'),
          'tenure'  => ($is_new ? ($cpa_cover == 'Y' ? '3' : '0') : ($is_indivisual ? ($cpa_cover == 'Y' ? '1' : '0') : '0'))
        ]
      ]
    ];
    // quick quote service input


    $additional_data = [
      'enquiryId' => $enquiryId,
      'headers' => [],
      'requestMethod' => 'post',
      'requestType' => 'json',
      'section' => $productData->product_sub_type_code,
      'method' => 'Premium Calculation',
      'transaction_type' => 'proposal',
      'productName' => $productData->product_name,
    ];

    $inputArray = [
      'QDATA' => json_encode($input_array),
      'SRC' => config('constants.IcConstants.tata_aig.SRC'),
      'T' => config('constants.IcConstants.tata_aig.TOKEN'),
      'productid' => config('constants.IcConstants.tata_aig.PRODUCT_ID'),
    ];

    $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE'), $inputArray, 'tata_aig', $additional_data);
    $response = $get_response['response'];

    if(!$response){
      return camelCase([
        'premium_amount' => 0,
        'status' => false,
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'message' => 'Insurer Not Reachable',
      ]);
    }

    $response = json_decode($response, true);

    if ($response['data']['status'] == '0') {
      return camelCase([
        'premium_amount' => 0,
        'status' => false,
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'message' => $response['data']['message'],
      ]);
    }

    // pass idv
    $input_array_info['idv'] = $idv;
    $input_array_info['revised_idv'] = $idv;
    // pass idv

    //full quote service input
    $input_array = [
      "functionality" => "validatequote",
      "quote_type" => "full",
      "vehicle" => $input_array_info,
      "cover" => [
        "C1" => [
          "opted" => (($is_liability) ? 'N' : 'Y')
        ],
        "C2" => [
          "opted" => ($is_od ? 'N' : 'Y')
        ],
        "C3" => [
          "opted"   => (($is_indivisual || !$is_od) ? $cpa_cover : 'N'),
          'tenure'  => ($is_new ? ($cpa_cover == 'Y' ? '3' : '0') : ($is_indivisual ? ($cpa_cover == 'Y' ? '1' : '0') : '0'))
        ],
        "C4" => [
          "opted" => (!$is_liability ? $Electricalaccess : 'N'),
          "SI" => (!$is_liability ? $ElectricalaccessSI : '0')
        ],
        "C5" => [
          "opted" => (!$is_liability ? $NonElectricalaccess : 'N'),
          "SI" => (!$is_liability ? $NonElectricalaccessSI : '0')
        ],
        "C6" => [
          "opted" => "N",
          "SI" => ""
        ],
        "C7" => [
          "opted" => (!$is_liability ? $externalCNGKIT : 'N'),
          "SI" => (!$is_liability ? $externalCNGKITSI : '0'),  //"10000"
        ],
        "C8" => [
          "opted" => "N"
        ],
        "C10" => [
          "opted" => "N",
          "SI" => ""
        ],
        "C11" => [
          "opted" => $is_anti_theft
        ],
        "C12" => [
          "opted" => $is_tppd
        ],
        "C13" => [
          "opted" => "N"
        ],
        "C14" => [
          "opted" => "N"
        ],
        "C15" => [
          "opted" => "N",
          "perc" => ""
        ],
        "C17" => [
          "opted" => (!$is_od ? $PAforUnnamedPassenger : 'N'),
          "SI" => (!$is_od ? $PAforUnnamedPassengerSI : '0'),
          "persons" => (!$is_od ? $mmv->seatingcapacity : '0')
        ],
        "C18" => [
          "opted" => (!$is_od ? $llpaidDriver : 'N'),
          "persons" => (!$is_od ? '1' : '0'),
        ],
        "C29" => [
          "opted" => (!$is_od ? $externalCNGKIT : 'N')
        ],
        "C35" => [
          "opted" => $DepreciationReimbursement,
          "no_of_claims" => $NoOfClaimsDepreciation,
          "Deductibles" => "0"
        ],
        "C37" => [
          "opted" => $ConsumablesExpenses,
        ],
        "C38" => [
          "opted" => $ReturnToInvoice
        ],
        "C39" => [
          "opted" => $NCBProtectionCover
        ],
        "C40" => [
          "opted" => $RepairOfGlasPlastcFibNRubrGlas
        ],
        "C41" => [
          "opted" => $LossOfPersonalBelongings
        ],
        "C42" => [
          "opted" => $EmergTrnsprtAndHotelExpense
        ],
        "C43" => [
          "opted" => $KeyReplacement
        ],
        "C44" => [
          "opted" => $EngineSecure
        ],
        "C45" => [
          "opted" => $TyreSecure
        ],
        "C47" => [
          "opted" => $RoadsideAssistance
        ],
        "C48" => [
          "opted" => "N", // $EmergTrnsprtAndHotelExpense,
          "SI" => null
        ],
        "C49" => [
          "opted" => "N",
          "SI" => null
        ],
        "C50" => [
          "opted" => "N", //$PAforaddionaldPaidDriver,
          "SI" => "", //$PAforaddionaldPaidDriverSI
        ],
        "C51" => [
          "opted" => "N",
          "SI" => NULL,
        ]
      ]
    ];

    //   full quote resuest
    $inputArray = [
      'QDATA' => json_encode($input_array),
      'SRC' => config('constants.IcConstants.tata_aig.SRC'),
      'T' => config('constants.IcConstants.tata_aig.TOKEN'),
      'productid' => config('constants.IcConstants.tata_aig.PRODUCT_ID'),
    ];

    $additional_data['method'] = 'Premium Calculation - Full Quote';
    $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_QUOTE'), $inputArray, 'tata_aig', $additional_data);
    $response = $get_response['response'];
    $response = json_decode($response, true);

    $quote_response = $response;
    if ($response['data']['status'] == '0') {
      return response()->json([
        'status' => true,
        'msg' => $response['data']['message'],
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
      ], 500);
    }

    if ($quote_log_data->quote_details['vehicle_owner_type'] == "I") {
      if ($proposal->gender == "M" || $proposal->gender == "Male") {
        $insured_prefix = 'Mr';
      } else {
        if (($proposal->gender == "F" || $proposal->gender == "Female") && $proposal->marital_status == "Single") {
          $insured_prefix = 'Miss';
        } else {
          $insured_prefix = 'Mrs';
        }
      }
    } else {
      $insured_prefix = 'M/S';
    }

    $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
    $insurer = keysToLower($insurer);

    if($is_od){
        $tp_insured         = $proposal_addtional_details['prepolicy']['tpInsuranceCompany'];
        $tp_insurer_name    = $proposal_addtional_details['prepolicy']['tpInsuranceCompanyName'];
        $tp_start_date      = $proposal_addtional_details['prepolicy']['tpStartDate'];
        $tp_end_date        = $proposal_addtional_details['prepolicy']['tpEndDate'];
        $tp_policy_no       = $proposal_addtional_details['prepolicy']['tpInsuranceNumber'];

        $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
        $tp_insurer_address = keysToLower($tp_insurer_address);
    }

    $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

    if($proposal_addtional_details['vehicle']['isVehicleFinance'])
    {
      $financerAgreementType = $proposal_addtional_details['vehicle']['financerAgreementType'];
      $nameOfFinancer = $proposal_addtional_details['vehicle']['nameOfFinancer'];
      $hypothecationCity = $proposal_addtional_details['vehicle']['hypothecationCity'];
    }

    // proposal site
    $proposal_input = [
      "functionality" => "validateproposal",
      "quotation_no" => $response['data']['quotationdata']['quotation_no'],
      "sol_id" => $sol_id,//config('constants.IcConstants.tata_aig.SOAL_ID'),
      "lead_id" => "",
      "pol_sdate" => $policy_start_date,
      "sp_name" => "",
      "sp_license" => "",
      "sp_place" => "",
      "productcod" => config('constants.IcConstants.tata_aig.PRODUCT_ID'),
      "customer" => [
        "salutation" => $insured_prefix,
        "client_type" => $customer_type,
        "organization_name" => "",
        "first_name" => $proposal->first_name,
        "middle_name" => "",
        "last_name" => $proposal->last_name,
        "gender" => $proposal->gender,
        "dob" => Carbon::parse($proposal->dob)->format('Ymd'),
        "marital_status" => $proposal->marital_status,
        "address_1" => $proposal->address_line1,
        "address_2" => $proposal->address_line2,
        "address_3" => $proposal->address_line3,
        "address_4" => "",
        "pincode" => $proposal->pincode,
        "account_no" => "",
        "cust_aadhaar" => ""
      ],
      "vehicle" => [
        "engine_no" => $proposal->engine_number,
        "chassis_no" => $proposal->chassis_number,
        "PucDecFlag" => "Y"
      ],
      "prevpolicy" => [
        "flag" => ($is_new ? 'N' : 'Y'),
        "code" => $previousInsurerList->code ?? '',
        "name" => $previousInsurerList->name ?? '',
        "address1" => $insurer->address_line_1 ?? '',
        "address2" => $insurer->address_line_2 ?? '',
        "address3" => "",
        "polno" => $proposal->previous_policy_number ?? '',
        "pincode" => $insurer->pin ?? '',
        "doc_name" => ""
      ],
      "financier" => [
        "type" => $financerAgreementType,
        "name" => $nameOfFinancer,
        "address" => $hypothecationCity,
        "loanacno" => ""
      ],
      "automobile" => [
        "flag" => "N",
        "number" => "",
        "name" => "",
        "expiry_date" => "19700101"
      ],
      "nominee" => [
        "name" => $proposal->nominee_name,
        "age" => $proposal->nominee_age ?? '18',
        "relation" => $proposal->nominee_relationship,
      ],
      "driver" => [
        "flag" => "N",
        "fname" => "",
        "lname" => "",
        "gender" => "",
        "age" => "",
        "drivingexp" => "",
        "marital_status" => ""
      ],
      "inspection" => [
        "flag" => "N",
        "number" => "",
        "date" => "",
        "agency_name" => "",
        "imagename_1" => "",
        "imagename_2" => "",
        "imagename_3" => ""
      ],
      "bundpolicy" => [
        "flag"          => 'N',
        "code"          => "ACKO",
        "name"          => "ACKO",
        "address1"      => "",
        "address2"      => "",
        "address3"      => "",
        "pincode"       => "400008",
        "bp_no"         => "",
        "bp_edate"      => "",
        "op_sdate"      => "",
        "op_edate"      => "",
        "cpap_sdate"    => "",
        "cpap_edate"    => "",
        "cpap_tenure"   => "",
        "tp_polnum"     => "",
        "tp_pol_sdate"  => "",
        "tp_pol_edate"  => ""
      ]
    ];
    if($is_od){
      $proposal_input["bundpolicy"] = [
        "flag"          => 'Y',
        "code"          => $tp_insured,
        "name"          => $tp_insurer_name,
        "address1"      => $tp_insurer_address->address_line_1,
        "address2"      => $tp_insurer_address->address_line_2,
        "address3"      => "",
        "pincode"       => $tp_insurer_address->pin,
        "bp_no"         => $proposal->previous_policy_number,
        "bp_edate"      => "",
        "op_sdate"      => Carbon::parse(date('Y-m-d',strtotime($proposal->prev_policy_expiry_date.'-1 year +1 day')))->format('Ymd'),
        "op_edate"      => Carbon::parse($proposal->prev_policy_expiry_date)->format('Ymd'),
        "cpap_sdate"    => "",
        "cpap_edate"    => "",
        "cpap_tenure"   => "",
        "tp_polnum"     => $tp_policy_no,
        "tp_pol_sdate"  => Carbon::parse($tp_start_date)->format('Ymd'),
        "tp_pol_edate"  => Carbon::parse($tp_end_date)->format('Ymd'),
      ];
    }

    $inputArray = [
      'PDATA' => json_encode($proposal_input),
      'SRC' => config('constants.IcConstants.tata_aig.SRC'),
      'T' => config('constants.IcConstants.tata_aig.TOKEN'),
      'product_code' => config('constants.IcConstants.tata_aig.PRODUCT_ID'),
      'THANKYOU_URL' => route('car.payment-confirm', ['tata_aig']),
    ];

    // additional data
    $additional_data['method'] = 'Proposal Submition';
    $additional_data['transaction_type'] = 'proposal';
    // additional data

    $get_response = getWsData(config('constants.IcConstants.tata_aig.END_POINT_URL_TATA_AIG_PROPOSAL'), $inputArray, 'tata_aig', $additional_data);
    $response = $get_response['response'];

    $response = json_decode($response, true);

    // return [$response, $proposal_input];

    if ($response['data']['status'] == "1") {
      $final_total_discount = (isset($quote_response['data']['C15']) ? ($quote_response['data']['C15']['premium']) : 0) + (isset($quote_response['data']['C11']) ? ($quote_response['data']['C11']['premium']) : 0) + (isset($quote_response['data']['C10']) ? ($quote_response['data']['C10']['premium']) : 0) + (isset($quote_response['data']['C12']) ? ($quote_response['data']['C12']['premium']) : 0);
      $proposal->proposal_no = $response['data']['proposalno'];
      $proposal->final_payable_amount = $response['data']['premium'];
      $proposal->policy_start_date = Carbon::parse($policy_start_date)->format('d-m-Y');
      $proposal->policy_end_date =  Carbon::parse($policy_end_date)->format('d-m-Y');
      $proposal->tp_start_date = Carbon::parse($tp_start_date)->format('d-m-Y');
      $proposal->tp_end_date =  Carbon::parse($tp_end_date)->format('d-m-Y');
      $proposal->od_premium = (isset($quote_response['data']['C1']) ? ($quote_response['data']['TOTALOD']) : 0) - $final_total_discount;
      $proposal->tp_premium = isset($quote_response['data']['C2']) ? ($quote_response['data']['NETTP']) : 0;
      $proposal->cpa_premium = isset($quote_response['data']['C3']) ? ($quote_response['data']['C3']['premium']) : 0;
      $proposal->addon_premium = (isset($quote_response['data']['NETADDON']) ? ($quote_response['data']['NETADDON']) : 0) + (isset($quote_response['data']['C47']) ? ($quote_response['data']['C47']['rate']) : 0);
      $proposal->ncb_discount = isset($quote_response['data']['C15']) ? ($quote_response['data']['C15']['premium']) : 0;
      $proposal->service_tax_amount = (isset($quote_response['data']['TAX']) ? ($quote_response['data']['TAX']['total_prem']) : 0)  + (isset($quote_response['data']['C47']) ? ($quote_response['data']['C47']['rate']) * 0.18 : 0);
      $proposal->total_premium = (isset($quote_response['data']['NETPREM']) ? ($quote_response['data']['NETPREM']) : 0) + (isset($quote_response['data']['C47']) ? ($quote_response['data']['C47']['rate']) : 0);
      $proposal->discount_percent = isset($quote_response['data']['discount']) ? ($quote_response['data']['discount']['rate']) : 0;
      $proposal->unique_proposal_id = $proposal_input["quotation_no"];
      $proposal->save();
      $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
      $data['ic_id'] = $productData->policy_id;
      $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
      $data['proposal_id'] = $proposal->user_proposal_id;
      updateJourneyStage($data);
      return response()->json([
        'status' => true,
        'msg' => 'Proposal Submited Successfully..!',
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'data' => camelCase([
          'proposal_no' => $response['data']['proposalno'],
          'data' => $proposal,
          'proposal' => $response,
          'quote' => $quote_response,
        ]),
      ]);
    } else {
      return response()->json([
        'status' => false,
        'msg' => $response['data']['message'],
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'message' => $response['data']['message'],
      ], 500);
    }
  }
}
