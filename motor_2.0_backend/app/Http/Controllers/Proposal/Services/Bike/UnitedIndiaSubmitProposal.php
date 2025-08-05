<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

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
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\UnitedIndiaPremiumDetailController;
use Illuminate\Http\Request;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class UnitedIndiaSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {

        $enquiryId     = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        
        $is_tp_breakin = (($premium_type == 'third_party_breakin') ? true : false);

        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }

        $is_package       = (($premium_type == 'comprehensive') ? true : false);
        $is_liability     = (($premium_type == 'third_party') ? true : false);
        $is_od            = (($premium_type == 'own_damage') ? true : false);
        $is_individual    = (($requestData->vehicle_owner_type == 'I') ? true : false);
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_zero_dep        = ($productData->zero_dep  == 0) ? true : false;

        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->first();

        $idv = $quote_log_data->idv;

        $mmv = get_mmv_details($productData, $requestData->version_id, 'united_india');
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return    [
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
        $mmv->idv       = $idv;

        $mmv->seating_capacity = 2;

        $rto_code       = $requestData->rto_code;
        
        $rto_data       = DB::table('united_india_rto_master')->where('TXT_RTA_CODE', strtr($rto_code, ['-' => '']))->first();
        $rto_data = keysToLower($rto_data);
        if(empty($rto_data))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request'=>[
                    'rto_code'=>$requestData->rto_code,
                    'rto_data' => $rto_data
                ]
            ];
        }
        $customer_type  = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $btype_code     = $requestData->business_type == "rollover" ? "2" : "1";
        $btype_name     = $requestData->business_type == "newbusiness" ? "New Business" : "Roll Over";

        $vehicleDate    = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1          = new  DateTime($vehicleDate);
        $date2          = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') :($requestData->business_type == "breakin" ? date('Y-m-d', strtotime('+3 day', time())): $requestData->previous_policy_expiry_date));
        $interval       = $date1->diff($date2);
        $age            = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age    = $interval->y;

        $vehicle_in_90_days = 0;

        $motor_manf_date = '01-' . $requestData->manufacture_year;

        $current_date       = date('Y-m-d');

        /* if (strtotime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date) < strtotime($current_date)) {
            $policy_start_date = date('Ymd');
        } */
        //nb and not sure cases not allowed

        if ($requestData->business_type == "newbusiness") {
            $policy_start_date  = date('d-m-Y');
            // $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
            if ($premium_type == 'comprehensive') {
                $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' + 1 year - 1 days'));
            } elseif ($premium_type == 'third_party') {
                $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 5 year'));
            } 
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 5 year'));
        } elseif($requestData->previous_policy_type == "Not sure"){
            $policy_start_date = $premium_type == 'third_party' ? date('d-m-Y',strtotime('+1 day')) : date('d-m-Y',strtotime('+2 day'));
            $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
            $tp_start_date =  $policy_start_date;
            $tp_end_date   = $policy_end_date;
            $requestData->applicable_ncb = 0;
            $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
        }else{
            $policy_start_date  = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
            }
            if ($requestData->business_type == 'breakin' && in_array($premium_type, ['comprehensive', 'own_damage'])) {
                $policy_start_date = date('d-m-Y', strtotime('+3 day', time()));
            }
            if($is_tp_breakin){
                $policy_start_date = date('d-m-Y', strtotime('+2 day', time()));
            }
            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
            $tp_start_date =  $policy_start_date;
            $tp_end_date   = $policy_end_date;
        }

        $vehicle_register_no    = explode('-', $proposal->vehicale_registration_number);
        $previousInsurerList    = PreviousInsurerList::where([
            'company_alias' => 'united_india',
            'code' => $proposal->previous_insurance_company
        ])->first();


        // addon
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $RepairOfGlasPlastcFibNRubrGlas = $DepreciationReimbursement = $NoOfClaimsDepreciation = $ConsumablesExpenses = $LossOfPersonalBelongings = $EngineSecure = $TyreSecure = $KeyReplacement = $RoadsideAssistance = $ReturnToInvoice = $NCBProtectionCover = $EmergTrnsprtAndHotelExpense = $ac_opted_in_pp = "N";



        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

        $PAforaddionaldPaidDriver = $PAforaddionaldPaidDriverSI = "N";
        $NCBProtectionCover = "N";



        $zero_dep                           = 'N';
        $consumable                         = 'N';
        $loss_of_belongings                 = 'N';
        $engine_secure                      = 'N';
        $tyre_secure                        = 'N';
        $key_replacement                    = 'N';
        $road_side_assistance               = 'N';
        $return_to_invoice                  = 'N';
        
        foreach ($addons as $key => $value) {
            if (in_array('Road Side Assistance', $value)) {
                $road_side_assistance = "Y";
            }
            if ($is_zero_dep && in_array('Zero Depreciation', $value)) {
                $zero_dep = "Y";
            }
            if (in_array('Key Replacement', $value)) {
                $key_replacement = "Y";
            }
            if (in_array('Engine Protector', $value)) {
                $engine_secure = "Y";
            }
            if (in_array('Consumable', $value)) {
                $consumable = "Y";
            }
            if (in_array('Tyre Secure', $value)) {
                $tyre_secure = "Y";
            }
            if (in_array('Return To Invoice', $value)) {
                $return_to_invoice = "Y";
            }
            if (in_array('Loss of Personal Belongings', $value)) {
                $loss_of_belongings = "Y";
            }
        }

        $is_zero_dep_applicable = true;

        if (($vehicle_age >= 5) || $vehicle_age == 4 && $interval->m >= 6)
        {
            $is_zero_dep_applicable = false;
        }

        $zero_dep               = (!$is_liability && ($zero_dep == 'Y') && $is_zero_dep_applicable) ? -1 : 0;
        $consumable             = (!$is_liability && $vehicle_age < 5 && ($consumable == 'Y')) ? 'Y' : 'N';
        $engine_secure          = (!$is_liability && $vehicle_age < 5 && ($engine_secure == 'Y')) ? 'Y' : 'N';
        $key_replacement        = (!$is_liability && $vehicle_age <= 5 && ($key_replacement == 'Y')) ? 'Y' : 'N';
        $loss_of_belongings     = 'N';
        $tyre_secure            = (!$is_liability && $vehicle_age <= 3 && ($tyre_secure == 'Y')) ? 'Y' : 'N';
        $road_side_assistance   =  $is_liability ? 'N' : $road_side_assistance;
        $return_to_invoice      = (!$is_liability && $is_zero_dep && $vehicle_age < 3 && ($return_to_invoice == 'Y')) ? 'Y' : 'N';


        if ($requestData->business_type == 'breakin')
        {
            $zero_dep                           = 'N';
            $consumable                         = 'N';
            $loss_of_belongings                 = 'N';
            $engine_secure                      = 'N';
            $tyre_secure                        = 'N';
            $key_replacement                    = 'N';
            $road_side_assistance               = 'N';
            $return_to_invoice                  = 'N';
        }
        $Electricalaccess = $ElectricalaccessSI = $externalCNGKIT = $PAforUnnamedPassenger = $PAforUnnamedPassengerSI = $PAforaddionaldPassenger = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccess = $NonElectricalaccessSI = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $llpaidDriver = $llpaidDriverSI = "N";

        $externalCNGKITSI = 0;

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
        $is_anti_theft = $is_voluntary_access = $is_tppd = 'N';
        $voluntary_excess_amt = 0;
        $tppd_amount = '100000';

        foreach ($discounts as $key => $value) {
            if (in_array('anti-theft device', $value) && !$is_liability)
            {
                $is_anti_theft = 'Y';
            }
            if (in_array('voluntary_insurer_discounts', $value) && isset($value['sumInsured']))
            {
                $is_voluntary_access = 'Y';
                $voluntary_excess_amt = $value['sumInsured'];
            }
            if (in_array('TPPD Cover', $value) && !$is_od)
            {
                $is_tppd = 'Y';
                $tppd_amount = '6000';
            }
        }

        $yn_paid_driver                     = 'N';
        if(!$is_od && $is_individual)
        {
            $yn_paid_driver                 = 'Y';
        }

        // cpa vehicle

        $proposal_addtional_details = json_decode($proposal->additional_details, true);
        // cpa vehicle
        $driver_declaration    = "ODD01";


        $cpa_cover                          = (($is_individual && !$is_od) ? -1 : 0);
        $cpa_cover_period                   = (($is_individual && !$is_od) ? 1 : 0);
        $txt_cover_period                   = (($is_individual && !$is_od) ? 1 : 0);
        $anti_theft_flag                    = (($is_anti_theft == 'Y') ? -1 : 0);


        $is_sapa = false;
        $is_valid_license       = 'Y';
        $sapa['applicable']     = 'N';
        $sapa['insured']        = '';
        $sapa['policy_no']      = '';
        $sapa['start_date']     = '';
        $sapa['end_date']       = '';
        if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
            $cpa_cover = -1;
            $cpa_cover_period = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : 1;
            $driver_declaration    = "ODD01";
        } elseif (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] != "") { 
            $cpa_cover = 0;
            if ($customer_type == 'Individual' && !$is_od) {
                $sapa['applicable'] = 'Y';#$proposal_addtional_details['prepolicy']['reason']
                if ($selected_addons->compulsory_personal_accident[0]['reason'] == "I have another motor policy with PA owner driver cover in my name") {
                    $is_sapa                = true;
                    $sapa['insured']        = $proposal_addtional_details['prepolicy']['cPAInsComp'] ?? '';
                    $sapa['policy_no']      = $proposal_addtional_details['prepolicy']['cPAPolicyNo'] ?? '';
                    $sapa['start_date']     = $proposal_addtional_details['prepolicy']['cpaPolicyStartDate'] ?? '';
                    $sapa['end_date']       = $proposal_addtional_details['prepolicy']['cpaPolicyEndDate'] ?? '';
                } elseif ($selected_addons->compulsory_personal_accident[0]['reason'] == "I have another PA policy with cover amount of INR 15 Lacs or more") {
                    $is_sapa                = true;
                    $sapa['insured']        = $proposal_addtional_details['prepolicy']['cPAInsComp'] ?? '';
                    $sapa['policy_no']      = $proposal_addtional_details['prepolicy']['cPAPolicyNo'] ?? '';
                    $sapa['start_date']     = $proposal_addtional_details['prepolicy']['cpaPolicyStartDate'] ?? '';
                    $sapa['end_date']       = $proposal_addtional_details['prepolicy']['cpaPolicyEndDate'] ?? '';
                } elseif ($selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
                    $driver_declaration    = "ODD02";
                    $is_valid_license      = 'N';
                } else {
                    $driver_declaration    = "ODD01";
                }
            }
        }
        if($is_od)
        {

            $tp_insured         = !empty($proposal->tp_insurance_company) ? $proposal->tp_insurance_company : '';
            $tp_insurer_name    = !empty($proposal->tp_insurance_company_name) ? $proposal->tp_insurance_company_name : '';
            $tp_start_date      = !empty($proposal->tp_start_date) ? $proposal->tp_start_date : '';
            $tp_end_date        = !empty($proposal->tp_end_date) ? $proposal->tp_end_date : '';
            $tp_policy_no       = !empty($proposal->tp_insurance_number) ? $proposal->tp_insurance_number : '';

            $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
            $tp_insurer_address = keysToLower($tp_insurer_address);
            if(empty($tp_insurer_address))
            {
                 $tp_insurer_address = '';
            }
        }

        //rto code DL condition
        if (strtoupper($proposal->vehicale_registration_number) == 'NEW') {
            
            $proposal_addtional_details['vehicle']['regNo1'] = 'NEW';
            $proposal_addtional_details['vehicle']['regNo2'] = '';
            $proposal_addtional_details['vehicle']['regNo3'] = '';
            $RegistrationNo_2 = '';
            $cpa_cover_period = isset($cpa_cover_period) ? $cpa_cover_period : 3;
        } else {
            $reg_no = explode('-', $proposal->vehicale_registration_number);
            $RegistrationNo_2 = $reg_no[1];
            if (isset($reg_no[0]) && ($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10) && strlen($reg_no[1]) >= 2)
            {
                $RegistrationNo_2 = substr($reg_no[1],1);
            }

            $RegistrationNo_4       = $proposal_addtional_details['vehicle']['regNo3'];
            $proposal_addtional_details['vehicle']['regNo3'] = ((strlen($RegistrationNo_4) == 1) ? '000'.$RegistrationNo_4 : ((strlen($RegistrationNo_4) == 2) ? '00'.$RegistrationNo_4 : ((strlen($RegistrationNo_4) == 3) ? '0'.$RegistrationNo_4 : $RegistrationNo_4)) );
        }

        // addon
        $is_aa_apllicable = false;

        $proposal_date = date('d/m/Y');

        if ($requestData->vehicle_owner_type == "I") {
            if (in_array(strtoupper($proposal->gender), ['M', 'MALE'])) {
                $insured_prefix = 'Mr';
            } else {
                if (in_array(strtoupper($proposal->gender), ['F', 'FEMALE']) && $proposal->marital_status == "Single") {
                    $insured_prefix = 'Mrs';
                } else {
                    $insured_prefix = 'Miss';
                }
            }
        } else {
            $insured_prefix = 'M/S';
        }

        $FuelType       = strtoupper($mmv->fuel_type);
        $cngLpgIDV      = $externalCNGKITSI ?? 0;

        $inbuiltCNGLPG = ($FuelType === 'CNG') ? -1 : 0; // As per git id 29421

        // if ($FuelType == 'PETROL' || $FuelType == 'DIESEL' || $FuelType == 'ELECTRIC')
        // {
        //     $inbuiltCNGLPG = 0;
        //     if ($cngLpgIDV == 0 || $cngLpgIDV == '')
        //     {
        //         $cngLpgIDV = 0;
        //     }
        // }elseif($FuelType == 'CNG'){
        //     $inbuiltCNGLPG = -1;
        // }else{
        //     $inbuiltCNGLPG = null;
        // }

        $legal_liability_to_paid_driver_flag = (($is_od || $llpaidDriver == "N") ? '0' : '1');

        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);
        $branch_details = DB::table('united_india_financier_branch_masters')->where('financier_branch_code', $proposal->financer_location)->first();

        $proposal_salutation     = ($is_individual ? (in_array(strtoupper($proposal->gender), ['M', 'MALE']) ? 'MR.' : (($proposal->marital_status == 'M') ? 'MRS.' : 'MS.')) : 'M/S');

        $quote_array =[
            'HEADER' => [
                'BIFUELKITODPREMIUM'                => 0,
                'BIFUELKITTPPREMIUM'                => 0,
                'CUR_DEALER_GROSS_PREM'             => 0,
                'CUR_DEALER_NET_OD_PREM'            => 0,
                'CUR_DEALER_NET_TP_PREM'            => 0,
                'CUR_DEALER_SERVICE_TAX'            => 0,

                // POLICY AND BUSINESS TYPE
                    'NUM_CLIENT_TYPE'                   => ($is_individual ? 'I' : 'C'),
                    'NUM_POLICY_TYPE'                   => ($is_package ? 'PackagePolicy' : ($is_liability ? 'LiabilityOnly' : 'StandAloneOD')),
                    'NUM_BUSINESS_CODE'                 => $btype_name,
                    'TXT_COVER_PERIOD'                  => ($is_liability ? '' : $txt_cover_period),

                // POLICY AND REGISSTRATION DATES
                    'DAT_PROPOSAL_DATE'                 => $proposal_date,
                    'DAT_DATE_OF_ISSUE_OF_POLICY'       => Carbon::parse($policy_start_date)->format('d/m/Y'),
                    'DAT_DATE_OF_EXPIRY_OF_POLICY'      => Carbon::parse($policy_end_date)->format('d/m/Y'),
                    'DAT_UTR_DATE'                      => '',
                    'DAT_DRIVING_LICENSE_EXP_DATE'      => '',
                    'DAT_AA_EXPIRY_DATE'                => '',

                // personal detals
                    'DAT_HOURS_EFFECTIVE_FROM'          => $requestData->business_type == "newbusiness" ? date('H:i') : '00:00',
                    'TXT_TITLE'                         => $proposal_salutation,
                    'TXT_NAME_OF_INSURED'               => ($proposal->first_name .' '.$proposal->last_name),
                    'TXT_EMAIL_ADDRESS'                 => $proposal->email,
                    'TXT_MOBILE'                        => $proposal->mobile_number,
                    'MEM_ADDRESS_OF_INSURED'            => ($proposal->address_line1 .' '.$proposal->address_line2 .' '.$proposal->address_line3),
                    'NUM_PIN_CODE'                      => (string)($proposal->pincode),
                    'TXT_GENDER'                        => ($is_individual ? $proposal->gender :""),
                    'TXT_DOB'                           => ($is_individual ? date('d/m/Y',strtotime($proposal->dob)) :""),
                    'TXT_PAN_NO'                        => $proposal->pan_number ?? '',
                    'TXT_GSTIN_NUMBER'                  => $proposal->gst_number ?? '',
                    'TXT_OCCUPATION'                    => $proposal->occupation,

                // RTO
                    'TXT_RTA_DESC'                      => $rto_data->txt_rta_code,
                    'TXT_VEHICLE_ZONE'                  => $rto_data->txt_registration_zone,

                // MMV
                    'NUM_CUBIC_CAPACITY'                => $mmv->fyntune_version['fuel_type'] == 'ELECTRIC' ? $mmv->fyntune_version['kw'] : $mmv->cubic_capacity,
                    'NUM_RGSTRD_SEATING_CAPACITY'       => $mmv->seating_capacity,
                    'TXT_FUEL'                          => strtoupper($mmv->fuel_type) == 'CNG' ? 'PETROL/CNG' : strtoupper($mmv->fuel_type),
                    'TXT_NAME_OF_MANUFACTURER'          => strtoupper(str_replace("-" ," " , $mmv->make)),
                    'TXT_OTHER_MAKE'                    => $mmv->model,
                    'TXT_VARIANT'                       => $mmv->variant,
                    'NUM_VEHICLE_MODEL_CODE'            => '',
                    'TXT_TYPE_BODY'                     => $mmv->body_type,

                // CPA
                    'YN_COMPULSORY_PA_DTLS'             => $cpa_cover,
                    'YN_VALID_DRIVING_LICENSE'          => $requestData->vehicle_owner_type == "I" ? $is_valid_license : 'N',
                    'TXT_CPA_COVER_PERIOD'              => (($cpa_cover) ? $cpa_cover_period : ''),

                    'TXT_NAME_OF_NOMINEE'               => ($is_individual ? ($proposal->nominee_name ?? '') : ''),
                    'TXT_RELATION_WITH_NOMINEE'         => ($is_individual ? ($proposal->nominee_relationship ?? '') : ''), //$nominee['relation'],
                    'NUM_VOLUNTARY_EXCESS_AMOUNT'       => 0, //$voluntary_deductable,
                    'NUM_IMPOSED_EXCESS_AMOUNT'         => '', //$voluntary_deductable,

                // NO CPA REASON
                    'YN_SCPA_PA_COVER_AVAILABLE'        => ($is_sapa ? 'Y' : 'N'),

                    "TXT_SCPA_PA_POL_INSURER_NAME"      => $sapa['insured'],
                    "TXT_SCPA_PA_POL_NUMBER"            => $sapa['policy_no'],
                    "DAT_SCPA_PA_POL_START_DATE"        => ($is_sapa ? Carbon::parse($sapa['start_date'])->format('d/m/Y') : ''),
                    "DAT_SCPA_PA_POL_END_DATE"          => ($is_sapa ? Carbon::parse($sapa['end_date'])->format('d/m/Y') : ''),

                // FINANCIER
                    'NUM_AGREEMENT_NAME_1'              => ($proposal->is_vehicle_finance ? $proposal->financer_agreement_type : ''),
                    'NUM_FINANCIER_NAME_1'              => ($proposal->is_vehicle_finance ? ($proposal->full_name_finance ?? ($proposal->name_of_financer ?? '')) : ''), 
                    'TXT_FIN_ACCOUNT_CODE_1'            => ($proposal->is_vehicle_finance ? ($branch_details->financier_code ?? '') : ''),
                    'TXT_FIN_BRANCH_NAME_1'             => ($proposal->is_vehicle_finance ? ($branch_details->branch_name ?? '') : ''),
                    'TXT_FINANCIER_BRANCH_ADDRESS1'     => ($proposal->is_vehicle_finance ? ($branch_details->branch_address ?? '') : ''),

                    'NUM_AGREEMENT_NAME_2'              => '',
                    'NUM_FINANCIER_NAME_2'              => '',
                    'TXT_FIN_ACCOUNT_CODE_2'            => '',
                    'TXT_FIN_BRANCH_NAME_2'             => '',
                    'TXT_FINANCIER_BRANCH_ADDRESS2'     => '',

                // ADDONS
                    'YN_NIL_DEPR_WITHOUT_EXCESS'        => $zero_dep,
                    'YN_RSA_COVER'                      => $road_side_assistance,
                    'YN_CONSUMABLE'                     => $consumable,
                    'YN_RTI_APPLICABLE'                 => $return_to_invoice,
                    'YN_ENGINE_GEAR_COVER_PLATINUM'     => $engine_secure,

                    'YN_LOSS_OF_KEY'                    => $key_replacement,
                    'NUM_LOSS_OF_KEY_SUM_INSURED'       => (($key_replacement == 'Y') ? '10000' : ''),

                    'YN_TYRE_RIM_PROTECTOR'             => $tyre_secure,
                    'NUM_TYRE_RIM_SUM_INSURED'          => (($tyre_secure == 'Y') ? '50000' : '0'),

                //COVERS
                    'NUM_IEV_CNG_VALUE'                 => $cngLpgIDV,
                    'YN_INBUILT_CNG'                    => $inbuiltCNGLPG,
                    'YN_INBUILT_LPG'                    => 0,
                    'NUM_IEV_LPG_VALUE'                 => '',

                    'YN_CLAIM'                          => (($requestData->is_claim == 'N') ? 'no' : 'yes'),
                    'YN_PAID_DRIVER'                    => $yn_paid_driver,
                    'CUR_BONUS_MALUS_PERCENT'           => $requestData->previous_ncb,

                    'NUM_LL1'                           => $legal_liability_to_paid_driver_flag,
                    'YN_ANTI_THEFT'                     => $anti_theft_flag,

                    'NUM_VOLUNTARY_EXCESS_AMOUNT'       => (string)(($is_voluntary_access == 'Y') ? $voluntary_excess_amt : 0),
                    'NUM_IMPOSED_EXCESS_AMOUNT'         => '',//((isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value != 0) ? $requestData->voluntary_excess_value : '0'),
                    
                    'NUM_IEV_ELEC_ACC_VALUE'            => (int)(($Electricalaccess == 'Y') ? $ElectricalaccessSI : '0'),
                    'ELECTRICALACCESSORIESPREM'         => (($Electricalaccess == 'Y') ? $ElectricalaccessSI : '0'),

                    'TXT_ELEC_DESC'                     => '',

                    'NUM_IEV_NON_ELEC_ACC_VALUE'        => (int)(($NonElectricalaccess == 'Y') ? $NonElectricalaccessSI : '0'),
                    'NONELECTRICALACCESSORIESPREM'      => (($NonElectricalaccess == 'Y') ? $NonElectricalaccessSI : '0'),

                    'TXT_NON_ELEC_DESC'                 => 0,
                    
                    'NUM_PA_UNNAMED_AMOUNT'             => ($requestData->unnamed_person_cover_si != '' && !$is_od) ? $requestData->unnamed_person_cover_si : '0' ,
                    'NUM_PA_UNNAMED_NUMBER'             => ((!$is_od) ? '1' : '0'),

                    #'NUM_TPPD_AMOUNT'                   => (!$is_od ? '6000' : '6000'),
                    'NUM_TPPD_AMOUNT'                   => $tppd_amount,

                // VEHICLE
                    'DAT_DATE_OF_REGISTRATION'          => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                    'DAT_DATE_OF_PURCHASE'              => Carbon::parse($vehicleDate)->format('d/m/Y'),
                    'NUM_YEAR_OF_MANUFACTURE'           => Carbon::parse('01-'.$requestData->manufacture_year)->format('Y'),

                    'TXT_REGISTRATION_NUMBER_1'         => $requestData->business_type == "newbusiness" ? 'NEW' : explode('-', $proposal_addtional_details['vehicle']['regNo1'])[0],
                    'TXT_REGISTRATION_NUMBER_2'         => $requestData->business_type == "newbusiness" ? '' : $RegistrationNo_2,
                    'TXT_REGISTRATION_NUMBER_3'         => $requestData->business_type == "newbusiness" ? '' : $proposal_addtional_details['vehicle']['regNo2'],
                    'TXT_REGISTRATION_NUMBER_4'         => $requestData->business_type == "newbusiness" ? '' : $proposal_addtional_details['vehicle']['regNo3'],

                    'TXT_ENGINE_NUMBER'                 => $proposal->engine_number,
                    'TXT_CHASSIS_NUMBER'                => $proposal->chassis_number,
                    'NUM_IEV_BASE_VALUE'                => ((!$is_liability) ? $proposal->idv : '0'),
                    'NUM_MONTH_OF_MANUFACTURE'          => Carbon::parse('01-'.$requestData->manufacture_year)->format('m'),

                // PREVIOUS INSURER
                    'TXT_PREVIOUS_INSURER'              => $proposal->previous_insurance_company,
                    'TXT_PREV_INSURER_CODE'             => $requestData->previous_policy_type == "Not sure" ? "ITGI" : $proposal->previous_insurance_company,
                    'DAT_PREV_POLICY_EXPIRY_DATE'       => $requestData->previous_policy_type == "Not sure" ? $requestData->previous_policy_expiry_date : Carbon::parse($proposal->prev_policy_expiry_date)->format('d/m/Y'),
                    'NUM_POLICY_NUMBER'                 => $requestData->previous_policy_type == "Not sure"  ? "1234567890" : $proposal->previous_policy_number,
                    'NUM_PREVIOUS_IDV'                  => '',

                //
                    'TXT_OEM_DEALER_CODE'               => config('constants.IcConstants.united_india.bike.BIKE_OEM_DEALER_CODE'),
                    'TXT_OEM_TRANSACTION_ID'            => '589872_200713154520',

                    'TXT_TRANSACTION_ID'                => '',

                    'TXT_MERCHANT_ID'                   => config('constants.IcConstants.united_india.bike.BIKE_PAYMENT_MERCHANT_ID'),

                    'NOCLAIMBONUSDISCOUNT'              => 0,
                    'NUM_COMPULSORY_EXCESS_AMOUNT'      => 0,
                    'NUM_DAYS_COVER_FOR_COURTESY'       => 0,
                    'NUM_GEOGRAPHICAL_EXTN_PREM'        => 0,

                    'NUM_NO_OF_NAMED_DRIVERS'           => 0,
                    'NUM_SPECIAL_DISCOUNT_RATE'         => (((!$is_liability) ? config('constants.IcConstants.united_india.bike.OD_DISCOUNT_RATE') : '0')),
                    'NUM_UTR_PAYMENT_AMOUNT'            => '',
                    'ODDiscount'                        => $premium_type != "own_damage" ? (config('IC.UNITED_INDIA.BIKE.OD_DISCOUNT')) : (config('IC.UNITED_INDIA.BIKE.OD_DISCOUNT_OD')),
                    'PAODPremium'                       => 0,
                    'TXT_AA_DISC_PREM'                  => '0',
                    'TXT_AA_FLAG'                       => '',
                    'TXT_MEDICLE_COVER_LIMIT'           => '',
                    'TXT_TELEPHONE'                     => '',
                    'YN_COMMERCIAL_FOR_PRIVATE'         => 0,
                    'YN_COURTESY_CAR'                   => 0,
                    'YN_DELETION_OF_IMT26'              => 0,
                    'YN_DRIVING_TUTION'                 => 0,
                    'YN_FOREIGN_EMBASSY'                => 0,
                    'YN_HANDICAPPED'                    => 0,
                    'YN_IMT32'                          => 0,
                    'YN_LIMITED_TO_OWN_PREMISES'        => 0,
                    'YN_MEDICLE_EXPENSE'                => 0,
                    'YN_PERSONAL_EFFECT'                => 0,

                    'NUM_LL2'                           => '',
                    'NUM_LL3'                           => '',
                    'NUM_PAID_UP_CAPITAL'               => '',
                    'NUM_PA_NAME1_AMOUNT'               => '',
                    'NUM_PA_NAME2_AMOUNT'               => '',
                    'NUM_PA_NAMED_AMOUNT'               => '',
                    'NUM_PA_NAMED_NUMBER'               => '',
                    'TXT_AA_MEMBERSHIP_NAME'            => '',
                    'TXT_AA_MEMBERSHIP_NUMBER'          => '',
                    'TXT_BANK_CODE'                     => '',
                    'TXT_BANK_NAME'                     => '',
                    'TXT_DRIVING_LICENSE_NO'            => '',
                    'TXT_PA_NAME1'                      => '',
                    'TXT_PA_NAME2'                      => '',
                    'TXT_UTR_NUMBER'                    => '', 
                    'NUM_IEV_FIBRE_TANK_VALUE'          => '',
                    'NUM_IEV_SIDECAR_VALUE'             => '',
                    'NUM_LD_CLEANER_CONDUCTOR'          => '',
                    'TXT_GEOG_AREA_EXTN_COUNTRY'        => '',
                    'TXT_LICENSE_ISSUING_AUTHORITY'     => '',
                    'TXT_MEMBERSHIP_CODE'               => '',
                    'TXT_NAMED_PA_NOMINEE1'             => '',
                    'TXT_NAMED_PA_NOMINEE2'             => '',
                    'TXT_VAHICLE_COLOR'                 => '',
                    'TXT_CKYC_NO'                       => ($proposal->ckyc_type == 'ckyc_number') ? $proposal->ckyc_type_value : '',
                    'TXT_CKYC_ADDL_INFO'                => ($proposal->ckyc_type == 'ckyc_number') ? '' : $proposal->ckyc_reference_id,
            ]
        ];
        if(strtoupper($requestData->fuel_type) == 'PETROL')
        {
            $quote_array['HEADER']['TXT_FUEL'] = 'PETROL';
        }
        if($is_liability){
            $quote_array['HEADER']['NUM_VOLUNTARY_EXCESS_AMOUNT'] = 0;
        }
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
        if($is_pos_enabled =='Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P'){
            $quote_array['HEADER']['TXT_POSP_CODE'] = $pos_data->relation_united_india;
            $quote_array['HEADER']['TXT_POSP_NAME'] = $pos_data->agent_name;
        }

        if($is_aa_apllicable){
            // AUTOMOBILE ASSOCIATION
            $quote_array['HEADER']['TXT_AA_FLAG']                       = 'Y';
            $quote_array['HEADER']['TXT_AA_MEMBERSHIP_NUMBER']          = $aa_membership_number ?? '';
            $quote_array['HEADER']['DAT_AA_EXPIRY_DATE']                = date('m/Y', strtotime($aa_exp_date));
            $quote_array['HEADER']['TXT_AA_MEMBERSHIP_NAME']            = $aa_membership_name ?? '';
            $quote_array['HEADER']['TXT_AA_DISC_PREM']                  = '200';
        }

        if($is_od)
        {
            $quote_array['HEADER']['TXT_TP_POLICY_NUMBER']             = $tp_policy_no;
            $quote_array['HEADER']['TXT_TP_POLICY_INSURER']            = $tp_insured;
            $quote_array['HEADER']['TXT_TP_POLICY_START_DATE']         = Carbon::parse($tp_start_date)->format('d/m/Y');
            $quote_array['HEADER']['TXT_TP_POLICY_END_DATE']           = Carbon::parse($tp_end_date)->format('d/m/Y');
            $quote_array['HEADER']['TXT_TP_POLICY_INSURER_ADDRESS']    = $tp_insurer_address->address_line_1.' '.$tp_insurer_address->address_line_2;
            $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE']        = $requestData->applicable_ncb == 0 ? 50 : 60;
        }
        // return ['quote_array' => $quote_array];

        if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy')
        {
            
        
        if ($is_new && !$is_liability) {
            $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_NEWBUSINESS_WITH_NCB'); //80
        } elseif ($premium_type == "comprehensive" && $interval->y <= 9 &&  $requestData->applicable_ncb != 0) {
            $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_COMPREHENSIVE_WITH_NCB');//80
        } elseif ($premium_type == "own_damage" && $interval->y <= 5 && $requestData->applicable_ncb != 0) {
            $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_OWN_DAMAGE_WITH_NCB');//70
        } elseif ($interval->y >= 15) {
            $Updated_discount_grid = 0;
        } else {
            $Updated_discount_grid = 0; // Default value if none of the above conditions are met
        }
        
      
        if ($requestData->applicable_ncb == 0 && !$is_new) {
            if ($premium_type == "comprehensive" && $interval->y <= 9 ) {
                $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_COMPREHENSIVE_WITHOUT_NCB');//70
            } elseif ($premium_type == "own_damage" && $interval->y <= 5 ) {
                $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_OWNDAMAGE_WITHOUT_NCB');//60
            }elseif ($interval->y >= 15) {
                $Updated_discount_grid = 0;
            } else {
                $Updated_discount_grid = 0; // Default value if none of the above conditions are met
            }
        }

            $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $Updated_discount_grid;
       
        // if(!$Updated_discount_grid){
        //     return  [
        //         'status'            => false,
        //         'message'           =>  $mmv->model .' Not Available In Discount Grid ',
        //     ];
        // }

}

        $request_container = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.uiic.com/">
            <soapenv:Header/>
            <soapenv:Body>
                <ws:calculatePremium>
                    <application>'.config('constants.IcConstants.united_india.bike.BIKE_APPLICATION_ID').'</application>
                    <userid>'.config('constants.IcConstants.united_india.bike.BIKE_USER_ID').'</userid>
                    <password>'.config('constants.IcConstants.united_india.bike.BIKE_USER_PASSWORD').'</password>
                    <proposalXml>
                        <![CDATA[#replace]]>
                    </proposalXml>
                    <productCode>'.config('constants.IcConstants.united_india.bike.BIKE_PRODUCT_CODE').'</productCode>
                    <subproductCode>'.config('constants.IcConstants.united_india.bike.BIKE_SUBPRODUCT_CODE').'</subproductCode>
                </ws:calculatePremium>
            </soapenv:Body>
            </soapenv:Envelope>
        ';

        // quick quote service input

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'xml',
            'section'           => 'Bike',
            'method'            => 'Premium Calculation',
            'transaction_type'  => 'proposal',
            'root_tag'          => 'ROOT',
            'soap_action'       => 'calculatePremium',
            'container'         => $request_container,
            'productName'       => $productData->product_name,
        ];

        $get_response = getWsData(config('constants.IcConstants.united_india.bike.BIKE_END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
        $response = $get_response['response'];

        $xhdf = ($response) ? 'true' : 'false';

        if ($response) {

            $quote_output = html_entity_decode($response);
            $quote_output = XmlToArray::convert($quote_output);

            $header = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER'];
            $error_message = $header['TXT_ERR_MSG'];

            if($error_message != [] && $error_message != ''){
                return [
                    'status'    => false,
                    'msg'       => $error_message,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'   => json_encode($error_message),
                    'quote_array' => $quote_array
                ];
            }

            $total_od_premium       = 0;
            $total_tp_premium       = 0;
            $od_premium             = 0;
            $tp_premium             = 0;
            $liability              = 0;
            $pa_owner               = 0; 
            $pa_unnamed             = 0;
            $lpg_cng_amount         = 0;
            $lpg_cng_tp_amount      = 0;
            $electrical_amount      = 0;
            $non_electrical_amount  = 0;
            $ncb_discount           = 0;
            $discount_amount        = 0;
            $zero_dep_amount        = '';
            $eng_prot               = '';
            $ncb_prot               = '';
            $rsa                    = '';
            $tyre_secure            = '';
            $key_replacement        = '';
            $return_to_invoice      = '';
            $consumable             = '';
            $bonus_discount         = 0;
            $anti_theft_discount    = 0;
            $kfc_od                 = 0;
            $kfc_tp                 = 0;
            $total_kfc              = 0;
            $automobile_association = 0;
            $tppd                   = 0;

            $base_cover = [
                'od_premium'            =>  0,
                'tp_premium'            =>  0,
                'pa_owner'              =>  0,
                'liability'             =>  0,
                'eng_prot'              =>  0,
                'return_to_invoice'     =>  0,
                'road_side_assistance'  =>  0,
                'zero_dep_amount'       =>  0,
                'medical_expense'       =>  0,
                'consumable'            =>  0,
                'key_replacement'       =>  0,
                'tyre_secure'           =>  0,
                'tppd'           =>  0,
            ];
            $base_cover_codes = [
                'od_premium'        =>  'Basic OD',
                'tp_premium'        =>  'Basic TP',
                'pa_owner'          =>  'PA Owner Driver',
                'liability'         =>  'LL to Paid Driver IMT 28',
                'eng_prot'          =>  'Engine and Gearbox Protection Platinum AddOn Cover',
                'return_to_invoice' =>  'Return To Invoice',
                'road_side_assistance' => 'Road Side Assistance',
                'zero_dep_amount'   =>  'Nil Depreciation Without Excess',
                'medical_expense'   =>  'Medical Expenses',
                'consumable'        =>  'Consumables Cover',
                'key_replacement'   =>  'Loss Of Key Cover',
                'tyre_secure'       =>  'Tyre And Rim Protector Cover',
                'tppd'              =>  'TPPD Discount',
            ];
            $base_cover_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];

            $discount_codes = [
                'bonus_discount'            =>  'No Claim Bonus Discount',
                'anti_theft_discount'       =>  'Anti-Theft Device - OD',
                'automobile_association'    =>  'Automobile Association Discount',
                'voluntary'                 =>  'Voluntary Excess Discount-OD',
            ];
            $match_arr = [
                'name'  => 'PropLoadingDiscount_Description',
                'value' => 'PropLoadingDiscount_CalculatedAmount',
            ];
            $discount = [
                'bonus_discount'            =>  0,
                'anti_theft_discount'       =>  0,
                'automobile_association'    =>  0,
                'voluntary'                 =>  0,
            ];

            $cng_codes = [
                'lpg_cng_tp_amount'     =>  'CNG Kit-TP',
                'lpg_cng_amount'        =>  'CNG Kit-OD',
            ];
            $cng_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];
            $cng = [
                'lpg_cng_tp_amount'     =>  0,
                'lpg_cng_amount'        =>  0,
            ];

            $worksheet = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['TXT_PRODUCT_USERDATA']['WorkSheet'];
            // print_pre([$worksheet]);
            
            // print_pre(['foreach block PropRisks_Co -> Risks', $worksheet['PropRisks_Col']['Risks']]);

            if(isset($worksheet['PropRisks_Col']['Risks'][0])){
                foreach ($worksheet['PropRisks_Col']['Risks'] as $risk_key => $risk_value)
                {
                    if(is_array($risk_value) && isset($risk_value['PropRisks_VehicleSIComponent']))
                    {
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Vehicle Base Value')
                        {
                            $base_cover = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);

                            if(!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0]))
                            {
                                $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                            $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                                else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount' || $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                            $detariff_discount =  $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_EndorsementAmount'] ?? $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                            }
                            else{
                                foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {
                                    if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP' )
                                    {

                                        if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                            if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                                if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        }
                                    }
                                    else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD' )
                                    {
                                        if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                            if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                                $detariffDiscount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] ?? 
                                                $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_Description'] ?? null;
                                                if ($detariffDiscount == 'Detariff Discount') {
                                                $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_EndorsementAmount'] ?? $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'CNG')
                        {
                            $cng = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Unnamed PA Cover')
                        {
                            $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Electrical Accessories')
                        {
                            $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Non-Electrical Accessories')
                        {
                            $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                        }
                    }
                }
            }else{
                $risk_value = $worksheet['PropRisks_Col']['Risks'];
                if(is_array($risk_value) && isset($risk_value['PropRisks_VehicleSIComponent'])){
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Vehicle Base Value')
                    {
                        $base_cover = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);

                        if(!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0]))
                        {
                            $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                            if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP')
                            {
                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                        if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                        $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }
                                }
                            }
                            else if ($v['PropCoverDetails_CoverGroups'] == 'Basic - OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic OD')
                            {
                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                        $detariffDiscount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] ?? 
                                        $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_Description'] ?? null;
                                        if ($detariffDiscount == 'Detariff Discount') {
                                        $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_EndorsementAmount'] ?? $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }
                                }
                            }
                        }
                        else{
                            foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                            $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                                else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            $detariffDiscount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] ?? 
                                        $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_Description'] ?? null;
                                            if ($detariffDiscount == 'Detariff Discount') {
                                          $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'][0]['PropLoadingDiscount_EndorsementAmount'] ?? $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'CNG')
                    {
                        $cng = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Unnamed PA Cover')
                    {
                        $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Electrical Accessories')
                    {
                        $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Non-Electrical Accessories')
                    {
                        $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                    }
                }
            }

            if (!$is_liability)
            {
                if ($requestData->applicable_ncb && $requestData->applicable_ncb != '0' && $requestData->applicable_ncb != '')
                {
                    $bonus_discount = isset($worksheet['PropLoadingDiscount_Col']['LoadingDiscount']['PropLoadingDiscount_CalculatedAmount'])? $worksheet['PropLoadingDiscount_Col']['LoadingDiscount']['PropLoadingDiscount_CalculatedAmount'] : '';
                }
            }

            if(!$is_liability){
                if(is_array($worksheet['PropLoadingDiscount_Col']) && !empty($worksheet['PropLoadingDiscount_Col'])){
                    $discount = UnitedIndiaSubmitProposal::united_india_cover_addon_values($worksheet['PropLoadingDiscount_Col']['LoadingDiscount'], $discount_codes, $match_arr, $discount);
                }
            }
            $idv = ($quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['NUM_IEV_BASE_VALUE']);

            $base_cover['tppd'] = $tppd;

            // echo "<pre>";print_r([
            //     $base_cover,
            //     $discount
            // ]);echo "</pre>";die();

            // proposal
            $quote_array['HEADER']['TXT_TRANSACTION_ID']    = $header['TXT_TRANSACTION_ID'];
            $request_container = '
                <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.uiic.com/">
                    <soapenv:Header/>
                    <soapenv:Body>
                        <ws:saveProposal>
                            <application>'.config('constants.IcConstants.united_india.bike.BIKE_APPLICATION_ID').'</application>
                            <userid>'.config('constants.IcConstants.united_india.bike.BIKE_USER_ID').'</userid>
                            <password>'.config('constants.IcConstants.united_india.bike.BIKE_USER_PASSWORD').'</password>
                            <proposalXml>
                                <![CDATA[#replace]]>
                            </proposalXml>
                            <productCode>'.config('constants.IcConstants.united_india.bike.BIKE_PRODUCT_CODE').'</productCode>
                            <subproductCode>'.config('constants.IcConstants.united_india.bike.BIKE_SUBPRODUCT_CODE').'</subproductCode>
                        </ws:saveProposal>
                    </soapenv:Body>
                </soapenv:Envelope>
            ';

            // quick quote service input

            $additional_data = [
                'enquiryId'         => $enquiryId,
                'headers'           => [],
                'requestMethod'     => 'post',
                'requestType'       => 'xml',
                'section'           => 'Bike',
                'method'            => 'Proposal Submit',
                'transaction_type'  => 'proposal',
                'root_tag'          => 'ROOT',
                'soap_action'       => 'saveProposal',
                'container'         => $request_container,
                'productName'       => $productData->product_name,
            ];

            $get_response = getWsData(config('constants.IcConstants.united_india.bike.BIKE_END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
            $proposal_response = $get_response['response'];



            if (!$response) {
                return [
                    'status'    => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'   => 'Insurer Not Reachable'
                ];
            }

            $addon_premium = (int) $base_cover['zero_dep_amount'] + (int) $base_cover['eng_prot'] + (int) $base_cover['return_to_invoice'] + (int) $base_cover['road_side_assistance'] + (int) $base_cover['consumable'];

            $proposal_output = html_entity_decode($proposal_response);
            $proposal_output = XmlToArray::convert($proposal_output);
            $proposal_header = $proposal_output['S:Body']['ns2:saveProposalResponse']['return']['ROOT']['HEADER'];
            $error_message = $proposal_header['TXT_ERR_MSG'];

            if($error_message != [] && $error_message != ''){
                return [
                    'status'    => false,
                    'msg'       => $error_message,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'   => json_encode($error_message)
                ];
            }
            $vehicleDetails = [
                'manufacture_name'  => $mmv->make,
                'model_name'        => $mmv->model,
                'version'           => $mmv->variant,
                'fuel_type'         => $mmv->fuel_type,
                'seating_capacity'  => $mmv->seating_capacity,
                'carrying_capacity' => $mmv->seating_capacity - 1,
                'cubic_capacity'    => $mmv->cubic_capacity,
                'gross_vehicle_weight' =>'',
                'vehicle_type'      => 'BIKE',
            ];
            $proposal->proposal_no = $header['TXT_TRANSACTION_ID'];

            $proposal_addtional_details['united_india']['transaction_id']     = $proposal_header['TXT_TRANSACTION_ID'];
            $proposal_addtional_details['united_india']['customer_id']        = $proposal_header['TXT_CUSTOMER_ID'];
            $proposal_addtional_details['united_india']['reference_number']   = $proposal_header['NUM_REFERENCE_NUMBER'];

            $final_total_discount               = (isset($proposal_header['NUM_TOTAL_DEDUCTION_OF_PREMIUM']) ? ($proposal_header['NUM_TOTAL_DEDUCTION_OF_PREMIUM']) : 0);

            $proposal->proposal_date = Carbon::parse(strtr($proposal_header['DAT_REFERENCE_DATE'], ['/' => '-']))->format('Y-m-d H:i:s');

            $proposal->final_payable_amount     = $proposal_header['CUR_FINAL_TOTAL_PREMIUM'];
            $proposal->policy_start_date        = Carbon::parse($policy_start_date)->format('d-m-Y');
            $proposal->policy_end_date          = Carbon::parse($policy_end_date)->format('d-m-Y');
            $proposal->tp_start_date            = Carbon::parse($tp_start_date)->format('d-m-Y');
            $proposal->tp_end_date              = Carbon::parse($tp_end_date)->format('d-m-Y');
            $proposal->od_premium               = (isset($proposal_header['CUR_NET_OD_PREMIUM']) ? ($proposal_header['CUR_NET_OD_PREMIUM']) - $addon_premium : 0);
            $proposal->tp_premium               = (isset($proposal_header['CUR_NET_TP_PREMIUM']) ? ($proposal_header['CUR_NET_TP_PREMIUM']) : 0);
            $proposal->cpa_premium              = $base_cover['pa_owner'];
            $proposal->addon_premium            = $addon_premium;
            $proposal->ncb_discount             = $discount['bonus_discount'];
            $proposal->service_tax_amount       = (isset($proposal_header['CUR_FINAL_SERVICE_TAX']) ? ($proposal_header['CUR_FINAL_SERVICE_TAX']) : 0);
            $proposal->total_premium            = (isset($proposal_header['CUR_NET_FINAL_PREMIUM']) ? ($proposal_header['CUR_NET_FINAL_PREMIUM']) : 0);
            $proposal->discount_percent         = ($is_liability ? 0 : $requestData->applicable_ncb);
            $proposal->total_discount           = $final_total_discount;
            $proposal->additional_details       = $proposal_addtional_details;

            $additionalDetailsData = json_decode($proposal->additional_details_data, true);
            $additionalDetailsData = array_merge($additionalDetailsData ?? [], $proposal_addtional_details);

            $proposal->additional_details_data = $additionalDetailsData;
            $proposal->ic_vehicle_details       =  $vehicleDetails;
            $proposal->save();

            $data['user_product_journey_id']    = customDecrypt($request['userProductJourneyId']);
            $data['ic_id']                      = $productData->policy_id;
            $data['stage']                      = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id']                = $proposal->user_proposal_id;

            updateJourneyStage($data);

            UnitedIndiaPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

            if ($proposal->is_ckyc_verified != 'Y') {
                $request_data = [
                    'companyAlias' => 'united_india',
                    'mode' =>  'ckyc',
                    'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    'lastProposalModifiedTime' =>  now()
                ];

                $ckycController = new CkycController;
                $response = $ckycController->ckycVerifications(new  Request($request_data));
                $response = $response->getOriginalContent();
                if(empty($response['data']['meta_data']['accessToken'])){
                    return[
                        'status' => false,
                        'msg' => $response['message'] ?? 'Token Generation Failed...!',
                    ];
                }
                $kyc_token = $response['data']['meta_data']['accessToken'];
            }
            return response()->json([
                'status' => true,
                'msg' => 'Proposal Submited Successfully..!',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => camelCase([
                    'proposal_no' => $proposal->proposal_no,
                    'finalpremium' => $proposal->final_payable_amount,
                    'token'=> $kyc_token ?? null
                ]),
                'premium_data' => [
                    'base_cover'                => $base_cover,
                    'cng'                       => $cng,
                    'discount'                  => $discount,
                    'pa_unnamed'                => $pa_unnamed,
                    'electrical_amount'         => $electrical_amount,
                    'non_electrical_amount'     => $non_electrical_amount,
                ],
            ]);
        }
        else{
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'   => 'Insurer Not Reachable'
            ];
        }
    }

    public static function united_india_cover_addon_values($value_arr, $cover_codes, $match_arr, $covers){
        if(!isset($value_arr[0]))
        {
            $value = $value_arr;
            foreach ($cover_codes as $k => $v) {
                if($value[$match_arr['name']] == $v)
                {
                    $covers[$k] = (int)$value[$match_arr['value']];
                }
            }
        }
        else
        {
            foreach ($value_arr as $key => $value)
            {
                foreach ($cover_codes as $k => $v) {
                    if($value[$match_arr['name']] == $v)
                    {
                        $covers[$k] = (int)$value[$match_arr['value']];
                    }
                }
            }
        }
        return $covers;
    }
}
