<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use Config;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

use App\Models\QuoteLog;
use App\Models\UserProposal;
use App\Models\ckycUploadDocuments;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\CvBreakinStatus;
use App\Models\PreviousInsurerList;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Car\TataAigPremiumDetailController;
use App\Models\ProposalExtraFields;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

include_once app_path() . "/Helpers/CarWebServiceHelper.php";
include_once app_path() . '/Helpers/CkycHelpers/TataAigCkycHelper.php';

class tataAigV2SubmitProposal
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

        $masterProduct  = MasterProduct::where("master_policy_id", $productData->policy_id)
            ->first();
        $premium_type   = DB::table("master_premium_type")
            ->where("id", $productData->premium_type_id)
            ->pluck("premium_type_code")
            ->first();
        $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
            ->first();

        $additionalDetailsData = json_decode($proposal->additional_details); 

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
        $is_individual  = $requestData->vehicle_owner_type == "I" ? true : false;
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_breakin = ($requestData->business_type == "breakin");

        $noPrevPolicy = ($requestData->previous_policy_type == 'Not sure');

        //Handling the new ckyc breakin case 
        $disable_breakin_ckyc = config('DISABLE_BREAKIN_CKYC_HANDLE', 'N');
        if ($is_breakin && $disable_breakin_ckyc != 'Y') {
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
        $idv = $quote_log_data->idv;

        $check_mmv = self::checkTataAigMMV($productData, $requestData->version_id);

        if(!$check_mmv['status'])
        {
            return $check_mmv;
        }

        $mmv = (object)$check_mmv['data'];

        if(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_MMV_CHANGE_TO_UAT') == 'Y')
        {
            $mmv = self::changeToUAT($mmv);
        }

        $customer_type = $is_individual ? "Individual" : "Organization";

        if($is_new){
            $policyStartDate  = strtotime($requestData->vehicle_register_date);//date('Y-m-d');

            if($is_liability){
                $policyStartDate  = strtotime($requestData->vehicle_register_date . '+ 1 day');
            }

            $policy_start_date = date('Y-m-d', $policyStartDate);

            // $policy_end_date = date('Y-m-d', strtotime($policy_start_date . '-1 days + 3 year'));
            if ($premium_type == 'comprehensive') {
                $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' + 1 year - 1 days'));
            } elseif ($premium_type == 'third_party') {
                $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 3 year'));
            }
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 3 year'));
        }
        else
        {
            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == "New" ? date("Y-m-d") : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = $interval->y * 12 + $interval->m + 1;
            $vehicle_age = $interval->y;

            $motor_manf_date = "01-" . $requestData->manufacture_year;

            $current_date = date("Y-m-d");

            if($is_breakin)
            {
                $policy_start_date = date("Y-m-d", strtotime(date('Y-m-d'). "+1 days"));
            }
            else
            {
                $policy_start_date = date("Y-m-d", strtotime($requestData->previous_policy_expiry_date . " + 1 days"));
            }

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date))
            {
                $policy_start_date = date("Y-m-d", strtotime(date('Y-m-d'). "+1 days"));
            }

            $policy_end_date = date("Y-m-d", strtotime($policy_start_date . " - 1 days + 1 year"));
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = $policy_end_date;
        }

        // $policy_start_date  = date('Ymd', strtotime($policy_start_date));
        // $policy_end_date    = date('Ymd', strtotime($policy_end_date));

        $vehicle_register_no = parseVehicleNumber($proposal->vehicale_registration_number);

        $previousInsurerList = PreviousInsurerList::where([
            "company_alias" => 'tata_aig_v2',
            "name" => $proposal->insurance_company_name,
        ])->first();

        // addon
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
        
        //Uat Plans array
        $uatPlansArray = [
            'SILVER'    => 'P1',
            'GOLD'      => 'P2',
            'PEARL'     => 'P3',
            'PEARL+'    => 'P4',
            'SAPPHIRE'  => 'P5',
            'SAPPHIREPLUS' => 'P6',
            'SAPPHIRE++'=> 'P7',
            'PLATINUM'  => 'P9', 
            'CORAL'     => 'P10',
            'PEARL++'   => 'P11' 
        ];
        $productIdentifier = $masterProduct->product_identifier ?? null;
        $planName = array_search($productIdentifier, array_flip($uatPlansArray), true);
        $planName = ($planName === false) ? "" : $planName;
        
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAPaidDriverConductorCleaner = $PAforaddionaldPassenger = $llpaidDriver = $NonElectricalaccess = "No";

        $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = '';

        $is_anti_theft = false;
        $is_automobile_assoc = false;
        $is_anti_theft_device_certified_by_arai = "false";
        $is_tppd = false;
        $tppd_amt = 0;
        $is_voluntary_access = 'No';
        $voluntary_excess_amt = '';

        $is_electrical = false;
        $is_non_electrical = false;
        $is_lpg_cng = false;

        foreach ($accessories as $key => $value)
        {
            if (in_array('Electrical Accessories', $value))
            {
                $is_electrical = true;
                $Electricalaccess = "Yes";
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value))
            {
                $is_non_electrical = true;
                $NonElectricalaccess = "Yes";
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value))
            {
                $is_lpg_cng = true;
                $externalCNGKIT = "Yes";
                $externalCNGKITSI = $value['sumInsured'];
                if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                    // $mmv->txt_fuel = 'External CNG';
                    // $mmv->txt_fuelcode = '5';
                }
            }

            if (in_array('PA To PaidDriver Conductor Cleaner', $value))
            {
                $PAPaidDriverConductorCleaner = "Yes";
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }

        foreach ($additional_covers as $key => $value)
        {
            if (in_array('PA cover for additional paid driver', $value))
            {
                $PAforaddionaldPaidDriver = "Yes";
                $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value))
            {
                $PAforUnnamedPassenger = "Yes";
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }

            if (in_array('LL paid driver', $value))
            {
                $llpaidDriver = "Yes";
                $llpaidDriverSI = $value['sumInsured'];
            }
        }

        foreach ($discounts as $key => $discount)
        {
            if ($discount['name'] == 'anti-theft device' && !$is_liability)
            {
                $is_anti_theft = true;
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            if ($discount['name'] == 'voluntary_insurer_discounts' && isset($discount['sumInsured']))
            {
                $is_voluntary_access = "Yes";
                $voluntary_excess_amt = $discount['sumInsured'];
            }

            if ($discount['name'] == 'TPPD Cover' && !$is_od)
            {
                $is_tppd = true;
                $tppd_amt = '9999';
            }
        }

        if(config('constants.IcConstants.tata_aig_v2.NO_VOLUNTARY_DISCOUNT') == 'Y')
        {
            $is_voluntary_access = false;
            $voluntary_excess_amt = '';
        }

        // cpa vehicle

        $proposal_additional_details = json_decode($proposal->additional_details, true);

        // cpa vehicle
        $driver_declaration = "None";
        $pa_owner_tenure = '';
        if (isset($selected_addons->compulsory_personal_accident[0]["name"]))
        {
            $cpa_cover = true;
            $driver_declaration = "None";
            
            $tenure = 1;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure'])? $selected_addons->compulsory_personal_accident[0]['tenure'] : $tenure;
            if($tenure === 3 || $tenure === '3')
            {
                $pa_owner_tenure = '3';
            }
            else
            {
                $pa_owner_tenure = '1';
            }
        }
        else
        {
            $cpa_cover = false;
            if ($customer_type == "Individual")
            {
                if (isset($proposal_additional_details["prepolicy"]["reason"]) && $proposal_additional_details["prepolicy"]["reason"] == "I have another motor policy with PA owner driver cover in my name")
                {
                    
                    $driver_declaration = "Other motor policy with CPA";
                }
                elseif (isset($proposal_additional_details["prepolicy"]["reason"]) && in_array($proposal_additional_details["prepolicy"]["reason"], ["I have another PA policy with cover amount greater than INR 15 Lacs", 'I have another PA policy with cover amount of INR 15 Lacs or more']))
                {
                    $driver_declaration = "Have standalone CPA >= 15 L";
                }
                elseif (isset($proposal_additional_details["prepolicy"]["reason"]) &&$proposal_additional_details["prepolicy"]["reason"] == "I do not have a valid driving license.")
                {
                    $driver_declaration = "No valid driving license";
                }
                else
                {
                    $driver_declaration = "None";
                }
            }
        }

        // ADDONS
        $applicableAddon = self::getApplicableAddons($masterProduct, $is_liability);

        $applicableAddon['RoadsideAssistance'] = "No";
        $applicableAddon['NCBProtectionCover'] = "No";
        foreach ($addons as $key => $value) {
            if (!$is_liability && in_array('Road Side Assistance', $value))
            {
                $applicableAddon['RoadsideAssistance'] = "Yes";
            }

            if (!$is_liability && in_array('NCB Protection', $value))
            {
                $applicableAddon['NCBProtectionCover'] = "Yes";
            }
        }

        if ($is_new || $requestData->applicable_ncb < 25) {//NCB protection cover is not allowed for NCB less than or equal to 20%
            $applicableAddon['NCBProtectionCover'] = "No";
        }
        // END ADDONS

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_aadhar = '';
        $pos_pan    = '';
        $sol_id     = "";//config('constants.IcConstants.tata_aig.SOAL_ID');
        $is_posp = 'N';
        $q_office_location = 0;

        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log_data->idv <= 5000000) {
            if(!empty($pos_data->pan_no)){
                $is_posp = 'Y';
                // $sol_id = $pos_data->pan_no;
                // if(!empty($pos_data->relation_tata_aig))
                // {
                //     $q_office_location = $pos_data->relation_tata_aig ;

                // }
                // else{
                //     $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
                // }
                $sol_id = $pos_data->relation_tata_aig;
                $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
            }
        }elseif(config('constants.motor.constants.IcConstants.tata_aig_v2.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2') == 'Y')
        {
            $is_posp = 'Y';
            $sol_id     = "840372";
            $q_office_location = 90200;
        }
        else
        {
            $is_pos = 'N';
        }

        if($is_od){
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
            ->where('txt_rto_code', 'like', '%'.$rto_code[0].$rto_code[1].'%')
            ->first();

        $ownership_count = ProposalExtraFields::where('enquiry_id', $enquiryId)->value('vahan_serial_number_count');
        $token_response = self::getToken($enquiryId, $productData);

        if(!$token_response['status'])
        {

            return $token_response;
        }

        if(config('constants.IcConstants.tata_aig_v2.NO_ANTITHEFT') == 'Y')
        {
            $is_anti_theft = false;
        }

        if(config('constants.IcConstants.tata_aig_v2.NO_NCB_PROTECTION') == 'Y')
        {
            $applicableAddon['NCBProtectionCover'] = 'No';
        }

        if(in_array(strtoupper($mmv->txt_segmenttype), ['MINI','COMPACT', 'MPS SUV', 'MPV SUV', 'MID SIZE']))
        {
            $engineProtectOption = 'WITH DEDUCTIBLE';
        }
        else
        {
            $engineProtectOption = 'WITHOUT DEDUCTIBLE';
        }

        $quoteRequest = [
            'quote_id'                      => '',

            'pol_plan_variant'              => ($is_package ? ($is_new ? 'PackagePolicy' : 'PackagePolicy') : ($is_liability ? ($is_new ? 'Standalone TP' : 'Standalone TP') : 'Standalone OD')),
            'pol_plan_id'                   => ($is_package ? ($is_new ? '04' : '02') : ($is_liability ? ($is_new ? '03' : '01') : '05')),

            'q_producer_code'               => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_PRODUCER_CODE'),
            'q_producer_email'              => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_PRODUCER_EMAIL'),

            'business_type_no'              => ($is_new ? '01' : '03'),
            'product_code'                  => '3184',
            'product_id'                    => 'M300000000001',
            'product_name'                  => 'Private Car',

            'proposer_type'                 => $customer_type,

            '__finalize'                    => '0',

            'add_towing'                    => 'No',
            'add_towing_amount'             => '',
            'allowance_days_accident'       => '',
            'allowance_days_loss'           => '',

            // DISCOUNTS
            'tppd_discount'                 => 'No',    //$is_tppd ? 'Yes' : 'No', #Commenting Git #33884  
            'antitheft_cover'               => $is_anti_theft ? 'Yes' : 'No',
            'automobile_association_cover'  => $is_automobile_assoc ? 'Yes' : 'No',
            'voluntary_amount'              => (string)($voluntary_excess_amt),
            // END DISCOUNT

            'cng_lpg_cover'                 => (string)($externalCNGKIT),
            'cng_lpg_si'                    => ($is_liability ? '0' : (string)($externalCNGKITSI)),

            // ASSESORIES
            'electrical_si'                 => (string)($ElectricalaccessSI),
            'non_electrical_si'             => (string)($NonElectricalaccessSI),
            // END ASSESORIES

            // COVERS
            'pa_named'                      => 'No',

            'pa_paid'                       => 'No',
            'pa_paid_no'                    => '',
            'pa_paid_si'                    => '',

            'pa_owner'                      => (($is_individual && !$is_od) ? ($cpa_cover ? 'true' : 'false') : 'false'),
            'pa_owner_declaration'          => $driver_declaration,
            'pa_owner_tenure'               => $pa_owner_tenure,

            'pa_unnamed'                    => 'No',

            'll_paid'                       => 'No',
            // END COVERS

            // ADDONS
            'repair_glass'                  => $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'],
            'return_invoice'                => $applicableAddon['ReturnToInvoice'],
            'rsa'                           => $applicableAddon['RoadsideAssistance'],
            'emergency_expense'             => $applicableAddon['EmergTrnsprtAndHotelExpense'],
            'consumbale_expense'            => $applicableAddon['ConsumablesExpenses'],
            'key_replace'                   => $applicableAddon['KeyReplacement'],
            'personal_loss'                 => $applicableAddon['LossOfPersonalBelongings'],

            'tyre_secure'                   => $applicableAddon['TyreSecure'],
            'tyre_secure_options'           => 'REPLACEMENT BASIS',// 'DEPRECIATION BASIS'

            'engine_secure'                 => $applicableAddon['EngineSecure'],
            'engine_secure_options'         => $is_liability ? '' : $engineProtectOption,

            'dep_reimburse'                 => $applicableAddon['DepreciationReimbursement'],
            'dep_reimburse_claims'          => $applicableAddon['NoOfClaimsDepreciation'],

            'ncb_protection'                => $applicableAddon['NCBProtectionCover'],
            'ncb_no_of_claims'              => '',

            // END ADDONS

            'claim_last'                    => (($is_new || $noPrevPolicy) ? 'false' : (($requestData->is_claim == 'N' || $is_liability) ? 'false' : 'true')),
            'claim_last_amount'             => null,
            'claim_last_count'              => null,

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

            'motor_plan_opted'              => $masterProduct->product_identifier,
            'motor_plan_opted_no'           => $applicableAddon['motorPlanOptedNo'],

            // 'plan_opted'                    => $masterProduct->product_identifier,
            // 'plan_opted_no'                 => $applicableAddon['motorPlanOptedNo'],

            'own_premises'                  => 'No',

            'place_reg'                     => $rto_data->txt_rtolocation_name,
            'place_reg_no'                  => $rto_data->txt_rtolocation_code,

            'pre_pol_ncb'                   => (($is_new || $noPrevPolicy) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
            'pre_pol_protect_ncb'           => null,
            'prev_pol_type'                 => (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package' : (in_array($requestData->previous_policy_type, [ 'Own-damage']) ? 'Standalone OD' :'Liability'))),

            'proposer_pincode'              => (string)($proposal->pincode),

            "regno_1"                       => $vehicle_register_no[0] ?? "",
            "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")),// (string)($vehicle_register_no[1] ?? ""), (string)($vehicle_register_no[1] ?? ""),
            "regno_3"                       => $vehicle_register_no[2] ?? "",
            "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),

            'uw_discount'                   => '',
            'uw_loading'                    => '',
            'uw_remarks'                    => '',

            'vehicle_blind'                 => 'No',
            'vehicle_idv'                   => '',

            'vehicle_make'                  => $mmv->manufacturer,
            'vehicle_make_no'               => (int)($mmv->manufacturercode),
            'vehicle_model'                 => $mmv->vehiclemodel,
            'vehicle_model_no'              => (int)($mmv->num_parent_model_code),
            'vehicle_variant'               => $mmv->txt_varient,
            'vehicle_variant_no'            => $mmv->vehiclemodelcode,

            'source'                        => 'P',
            'vintage_car'                   => 'No',
            'proposer_email'                => strtolower($proposal->email)

        ];

        $quoteRequest['repair_glass']                  = 'No';//$applicableAddon['RepairOfGlasPlastcFibNRubrGlas'];
        $quoteRequest['return_invoice']                = 'No';//$applicableAddon['ReturnToInvoice'];
        $quoteRequest['emergency_expense']             = 'No';//$applicableAddon['EmergTrnsprtAndHotelExpense'];
        $quoteRequest['consumbale_expense']            = 'No';//$applicableAddon['ConsumablesExpenses'];
        $quoteRequest['key_replace']                   = 'No';//$applicableAddon['KeyReplacement'];
        $quoteRequest['personal_loss']                 = 'No';//$applicableAddon['LossOfPersonalBelongings'];
        $quoteRequest['tyre_secure']                   = 'No';//$applicableAddon['TyreSecure'];
        $quoteRequest['engine_secure']                 = 'No';//$applicableAddon['EngineSecure'];
        $quoteRequest['dep_reimburse']                 = 'No';//$applicableAddon['DepreciationReimbursement'];
        $quoteRequest['rc_owner_sr'] = (string)($ownership_count) ?? "";

        if($is_posp == "Y")
        {
            $quoteRequest['is_posp'] = $is_posp;
            $quoteRequest['sol_id'] = $sol_id;
            $quoteRequest['q_office_location'] = $q_office_location;
        }

        if(!$is_new || !$noPrevPolicy)
        {
            $quoteRequest['no_past_pol'] = 'N';
        }
        else
        {
            $quoteRequest['no_past_pol'] = 'Y';
        }

        if(!$is_new)
        {
            $quoteRequest['no_past_pol'] = 'N';
        }
        if($noPrevPolicy){
            $quoteRequest['no_past_pol'] = 'Y';
        }
    

        if($applicableAddon['NCBProtectionCover'] == 'Yes')
        {
            $quoteRequest['ncb_no_of_claims'] = '1';
        }
        if(config('constants.motorConstant.SMS_FOLDER') === 'edme'){
        $quoteRequest["fleetCode"] = config('constant.IcConstant.TATA_AIG_V2_CAR_FLEET_CODE');
        $quoteRequest["fleetName"] = config('constant.IcConstant.TATA_AIG_V2_CAR_FLEET_NAME');
        $quoteRequest["fleetOpted"] = "true";
        $quoteRequest["vehicle_chassis"] = $proposal->chassis_number;
        $quoteRequest["vehicle_engine"] = $proposal->engine_number;
        $quoteRequest["optionForCalculation"] = "Yearly";
        }
        //checking last addons
        $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsConsumable_Cover = $PreviousPolicy_IsReturnToInvoice_Cover = $PreviousPolicy_IsTyre_Cover = $PreviousPolicy_IsEngine_Cover = $PreviousPolicy_IsLpgCng_Cover = $is_breakin_case =  false;
        if (!empty($proposal->previous_policy_addons_list)) {
            $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
            foreach ($previous_policy_addons_list as $key => $value) {
                if ($key == 'zeroDepreciation' && $value) {
                    $PreviousPolicy_IsZeroDept_Cover = true;
                } else if ($key == 'consumables' && $value) {
                    $PreviousPolicy_IsConsumable_Cover = true;
                }else if ($key == 'tyreSecure' && $value) {
                    $PreviousPolicy_IsTyre_Cover = true;
                }else if ($key == 'engineProtector' && $value) {
                    $PreviousPolicy_IsEngine_Cover = true;
                }else if ($key == 'externalBiKit' && $value) {
                    $PreviousPolicy_IsLpgCng_Cover = true;
                }else if ($key == 'returnToInvoice' && $value) {
                    $PreviousPolicy_IsReturnToInvoice_Cover = true;
                }
            }
        }
        if(!$is_new && !$noPrevPolicy)
        {
            if ($is_lpg_cng) {
                $quoteRequest['prev_cnglpg'] = 'Yes';
                if (!$PreviousPolicy_IsLpgCng_Cover && !$is_liability) {
                    $is_breakin_case =  true;
                    $quoteRequest['prev_cnglpg'] = 'No';
                }
            }

            if($is_liability)
            {
                $quoteRequest['prev_cnglpg'] = 'No';
            }

            if ($applicableAddon['ConsumablesExpenses'] == 'Yes') {
                $quoteRequest['prev_consumable'] = 'Yes';
                if (!$PreviousPolicy_IsConsumable_Cover) {
                    $quoteRequest['prev_consumable'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['ReturnToInvoice'] == 'Yes') {
                $quoteRequest['prev_rti'] = 'Yes';
                if (!$PreviousPolicy_IsReturnToInvoice_Cover) {
                    $quoteRequest['prev_rti'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['TyreSecure'] == 'Yes') {
                $quoteRequest['prev_tyre'] = 'Yes';
                if (!$PreviousPolicy_IsTyre_Cover) {
                    $quoteRequest['prev_tyre'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['EngineSecure'] == 'Yes') {
                $quoteRequest['prev_engine'] = 'Yes';
                if (!$PreviousPolicy_IsEngine_Cover) {
                    $quoteRequest['prev_engine'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['DepreciationReimbursement'] == 'Yes') {
                $quoteRequest['prev_dep'] = 'Yes';
                if (!$PreviousPolicy_IsZeroDept_Cover) {
                    $quoteRequest['prev_dep'] = 'No';
                    $is_breakin_case =  true;
                }
            }
        }

        if(!$is_od)
        {
            if($PAforUnnamedPassenger == 'Yes')
            {
                $quoteRequest['pa_unnamed'] = $PAforUnnamedPassenger;
                $quoteRequest['pa_unnamed_csi'] = '';
                $quoteRequest['pa_unnamed_no'] = (string)($mmv->seatingcapacity);
                $quoteRequest['pa_unnamed_si'] = (string)$PAforUnnamedPassengerSI;
            }
            if($llpaidDriver == 'Yes')
            {
                $quoteRequest['ll_paid'] = $llpaidDriver;
                $quoteRequest['ll_paid_no'] = '1';
            }
            if($PAforaddionaldPaidDriver == 'Yes')
            {
                $quoteRequest['pa_paid'] = $PAforaddionaldPaidDriver;
                $quoteRequest['pa_paid_no'] = '1';
                $quoteRequest['pa_paid_si'] = $PAforaddionaldPaidDriverSI;
            }
        }

        if($is_od)
        {
            $quoteRequest['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
            $quoteRequest['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');

            $quoteRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
            $quoteRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
        }

        // $quoteRequest['vehicle_make_no'] = 113;
        // $quoteRequest['vehicle_model_no'] = 10005;
        // $quoteRequest['vehicle_variant_no'] = '100070';
        if(env('APP_ENV') == 'local'){
            $quoteRequest['motor_plan_opted_no'] = $planName;
        }


        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Calculation - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
        $quoteResponse = $get_response['response'];

        if(!($quoteResponse && $quoteResponse != '' && $quoteResponse != null))
        {
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

        if(empty($quoteResponse))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        if(isset($quoteResponse['message']) && ($quoteResponse['message'] == 'Endpoint request timed out' || $quoteResponse['message'] == 'Too Many Requests' ) )
        {
            return [
                'status'        => false,
                'msg'           => $quoteResponse['message'],
                'webservice_id' => $get_response['webservice_id'],
                'table'         => $get_response['table'],
                'Request'       => $quoteRequest,
                'stage'         => 'quote'
            ];
            
        }

        if(isset($quoteResponse['status']) && $quoteResponse['status'] != 200)
        {
            if(!isset($quoteResponse['message_txt']))
            {
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

        // echo "<pre>";print_r([$quoteResponse]);echo "</pre>";die();

        $quoteResponse2 = $quoteResponse;
        $quoteResponse = $quoteResponse['data'][0]['data'];

        if($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true')
        {
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
        $max_idv    = ($is_liability ? 0 : $quoteResponse['max_idv']);
        $min_idv    = ($is_liability ? 0 : $quoteResponse['min_idv']);

        $quoteRequest['vehicle_idv'] = (string)($idv);
        $quoteRequest['__finalize'] = '1';

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Recalculation - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_QUOTE'), $quoteRequest, 'tata_aig_v2', $additional_data);
        $quoteResponse = $get_response['response'];

        if(!($quoteResponse && $quoteResponse != '' && $quoteResponse != null))
        {
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

        if(empty($quoteResponse))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }

        if(isset($quoteResponse['status']) && $quoteResponse['status'] != 200)
        {
            if(!isset($quoteResponse['message_txt']))
            {
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

        $premWebServiceId = $get_response['webservice_id'];

        $quoteResponse2 = $quoteResponse;

        $quoteResponse = $quoteResponse['data'][0]['data'];

        if($quoteResponse2['data'][0]['pol_dlts']['refferal'] == 'true')
        {
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
        $max_idv    = $quoteResponse['max_idv'];
        $min_idv    = $quoteResponse['min_idv'];

        $policy_id = $quoteResponse['policy_id'];
        $quote_id = $quoteResponse['quote_id'];
        $quote_no = $quoteResponse['quote_no'];
        $proposal_id = $quoteResponse['proposal_id'];
        $product_id = $quoteResponse['product_id'];

        $totalOdPremium = $quoteResponse['premium_break_up']['total_od_premium'];
        $totalAddons    = $quoteResponse['premium_break_up']['total_addOns'];
        $totalTpPremium = $quoteResponse['premium_break_up']['total_tp_premium'];

        $premium_without_gst = $quoteResponse['premium_break_up']['final_premium']; //net premium as without gst value use final premium
        $total_payable   = $quoteResponse['premium_break_up']['net_premium'];


        $basic_od       = $totalOdPremium['od']['basic_od'];
        $total_od       = $totalOdPremium['total_od'];
        $non_electrical = $totalOdPremium['od']['non_electrical_prem'];
        $electrical     = $totalOdPremium['od']['electrical_prem'];
        $lpg_cng_od     = $totalOdPremium['od']['cng_lpg_od_prem'];

        $final_od_premium = $basic_od + $non_electrical + $electrical + $lpg_cng_od;


        $basic_tp       = $totalTpPremium['basic_tp'];
        $total_tp       = $totalTpPremium['total_tp'];
        $pa_unnamed     = $totalTpPremium['pa_unnamed_prem'];
        $ll_paid        = $totalTpPremium['ll_paid_prem'];
        $lpg_cng_tp     = $totalTpPremium['cng_lpg_tp_prem'];

        $pa_paid        = (int)(isset($quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem']) ? $quoteResponse2['data']['0']['pol_dlts']['pa_paid_prem'] : 0);

        $final_tp_premium = $basic_tp + $pa_unnamed + $ll_paid + $lpg_cng_tp + $pa_paid;

        $pa_owner       = $totalTpPremium['pa_owner_prem'];
        $tppd_discount  = $totalTpPremium['tppd_prem'];


        $anti_theft_amount       = $totalOdPremium['discount_od']['atd_disc_prem'];
        $automoble_amount       = $totalOdPremium['discount_od']['aam_disc_prem'];
        $voluntary_deductible   = $totalOdPremium['discount_od']['vd_disc_prem'];
        $ncb_discount_amount    = $totalOdPremium['discount_od']['ncb_prem'];

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

        $emergency_expense_amount   = $totalAddons['emergency_expense_prem'];
        $repair_glass_prem          = $totalAddons['repair_glass_prem'];

        $final_addon_amount         = $totalAddons['total_addon'];

        if ($is_individual) {
            if ($proposal->gender == "M" || $proposal->gender == "Male")
            {
                $gender = 'Male';
                $insured_prefix = 'Mr';
            }
            else
            {
                $gender = 'Female';
                if ($proposal->marital_status != "Single")
                {
                    $insured_prefix = 'Mrs';
                }
                else
                {
                    $insured_prefix = 'Ms';
                }
            }
        }
        else
        {
            $gender = 'Others';
            $insured_prefix = 'M/s.';
        }

        $occupation = $is_individual ? $proposal_additional_details['owner']['occupation'] : '';

        $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

        if($proposal_additional_details['vehicle']['isVehicleFinance'])
        {
            $financerAgreementType = $proposal_additional_details['vehicle']['financerAgreementType'];
            $nameOfFinancer = $proposal_additional_details['vehicle']['nameOfFinancer'];
            $hypothecationCity = $proposal_additional_details['vehicle']['hypothecationCity'];
            if(isset($proposal_additional_details['vehicle']['financer_sel'][0]['name']))
            {
                $nameOfFinancer = $proposal_additional_details['vehicle']['financer_sel'][0]['name'];
            }
        }

        $pucExpiry = $pucNo = '';

        if(isset($proposal_additional_details['vehicle']['pucExpiry']))
        {
            $pucExpiry = Carbon::parse($proposal_additional_details['vehicle']['pucExpiry'])->format('Y-m-d');
        }
        if(isset($proposal_additional_details['vehicle']['pucNo']))
        {
            $pucNo = $proposal_additional_details['vehicle']['pucNo'];
        }

        if(is_numeric($nameOfFinancer))
        {
            $financeData   = DB::table("tata_aig_finance_master")
            ->where("code", $nameOfFinancer)
            ->first();
            if(!empty($financeData))
            {
                $nameOfFinancer = $financeData->name;
            }
        }

        $first_name = '';
        $middle_name = '';
        $last_name = '';

        $nameArray = $is_individual ? (explode(' ', trim($proposal->first_name.' '.$proposal->last_name))) : explode(' ', trim($proposal->first_name));

        $first_name = $nameArray[0];

        // for TATA f_name and l_name should only contain 1 word and rest will be in m_name
        if(count($nameArray) > 2){
            $last_name = end($nameArray);
            array_pop($nameArray);
            array_shift($nameArray);
            $middle_name = implode(' ', $nameArray);
        }
        else
        {
            $middle_name = '';
            if(env('APP_ENV') == 'local')
            {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '.');
            }else
            {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '');
            }
        }

        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 55,
            'address_2_limit'   => 55,         
            'address_3_limit'   => 55,         
        ];
        $getAddress = getAddress($address_data);

        
        $inspection_type_self = ($is_breakin ? (($proposal->inspection_type ?? '') == 'Manual' ? 'No' : 'Yes') : 'No');

        $proposalRequest = [
            'proposer_gender' => $gender,
            'proposer_marital' => $is_individual ? $proposal->marital_status :'',
            'proposer_fname' => $first_name,
            'proposer_mname' => $middle_name,
            'proposer_lname' => empty($last_name) ? '.' : $last_name,
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
            'proposer_dob' => $is_individual ? Carbon::parse($proposal->dob)->format('Y-m-d') : '',

            'vehicle_puc_expiry' => $pucExpiry,
            'vehicle_puc' => $pucNo,
            'vehicle_puc_declaration' => 'true',

            'pre_insurer_name' => (($is_new || $noPrevPolicy) ? '' : $previousInsurerList->code),
            'pre_insurer_no' => (($is_new || $noPrevPolicy) ? '' : $proposal->previous_policy_number),
            'pre_insurer_address' => '',//$is_new ? '' : $proposal_additional_details['prepolicy']['previousInsuranceCompany'],

            'financier_type' => $financerAgreementType,
            'financier_name' => $nameOfFinancer,
            'financier_address' => $hypothecationCity,

            'nominee_name' => (($is_individual && !$is_od) ? ($proposal->nominee_name ?? '') : ''),
            'nominee_relation' => (($is_individual && !$is_od) ? ($proposal->nominee_relationship ?? '') : ''),
            'nominee_age' => (($is_individual && !$is_od) ? (int)($proposal->nominee_age ?? '') : 0),

            'appointee_name' => '',
            'appointee_relation' => '',

            'proposal_id' => $proposal_id,
            'product_id' => $product_id,
            'quote_no' => $quote_no,

            'declaration' => 'Yes',

            'vehicle_chassis' => $proposal->chassis_number,
            'vehicle_engine' => $proposal->engine_number,

            'proposer_fullname' => ($is_individual ? ($proposal->first_name .' '.$proposal->last_name) : $proposal->first_name),

            'carriedOutBy' => $inspection_type_self,
            '__finalize' => '1',
            'is_posp'                       => "N",
            'sol_id'                        => "",
            'q_office_location'             => "",
        ];

        if($is_posp == "Y")
        {
            $proposalRequest['is_posp'] = $is_posp;
            $proposalRequest['sol_id'] = $sol_id;
            $proposalRequest['q_office_location'] = $q_office_location;
        }
        if($occupation == 'OTHER')
        {
            $proposalRequest['proposer_occupation_other'] = 'OTHER';
        }

        if($is_od)
        {
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
        
        //Commenting as per git #33843
        // if(config('constants.motorConstant.SMS_FOLDER') === 'edme'){
        //     $proposalRequest["fleetCode"] = config('constant.IcConstant.TATA_AIG_V2_CAR_FLEET_CODE');
        //     $proposalRequest["fleetName"] = config('constant.IcConstant.TATA_AIG_V2_CAR_FLEET_NAME');
        //     $proposalRequest["fleetOpted"] = "true";
        //     $proposalRequest["vehicle_chassis"] = $proposal->chassis_number;
        //     $proposalRequest["vehicle_engine"] = $proposal->engine_number;
        //     $proposalRequest["optionForCalculation"] = "Yearly";
        // }

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
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Proposal Submition - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_PROPOSAL'), $proposalRequest, 'tata_aig_v2', $additional_data);
        $proposalResponse = $get_response['response'];

        if(!($proposalResponse && $proposalResponse != '' && $proposalResponse != null))
        {
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

        if(empty($proposalResponse))
        {
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

        if(!isset($proposalResponse['status']) || $proposalResponse['status'] != 200 || empty($proposalResponse['data']))
        {
            if(!isset($proposalResponse['message_txt']) )
            {
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
        }
        else
        {

            $proposalResponse2  = $proposalResponse;
            // echo "<pre>";print_r([$proposalResponse, $proposalRequest]);echo "</pre>";die();
            $proposalResponse   = $proposalResponse['data'][0];

            $proposal->od_premium               = $total_od; //+ $final_addon_amount;
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
            $proposal->tp_start_date = Carbon::parse($tp_start_date)->format('d-m-Y');
            $proposal->tp_end_date =  Carbon::parse($tp_end_date)->format('d-m-Y');

            $tata_aig_v2_data = [
                'quote_no'       => $proposalResponse['quote_no'],
                'proposal_no'    => $proposalResponse['proposal_no'],
                'proposal_id'    => $proposalResponse['proposal_id'],
                'payment_id'     => $proposalResponse['payment_id'],
                'document_id'    => $proposalResponse['document_id'],
                'policy_id'      => $proposalResponse['policy_id'],
                'master_policy_id' => $productData->policy_id,
            ];

            $isBreakinInspectionRequired = (isset($proposalResponse['inspectionFlag']) && $proposalResponse['inspectionFlag'] == 'true') ? true : false;

            if($isBreakinInspectionRequired && !(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_INSPECTION_ENABLED') == 'Y'))
            {
                return [
                    'status'    => false,
                    'msg'       => 'Inspection is not allowed',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $proposalRequest,
                    'Request'   => $proposalResponse,
                    'stage'     => 'proposal'
                ];
            }
            else if($isBreakinInspectionRequired && empty($proposalResponse['ticket_number']))
            {
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
            if($is_breakin && $disable_breakin_ckyc != 'Y'){
                $proposal_additional_details['proposalSubmit'] = $proposalResponse;
                $proposal_additional_details['webserviceData'] = $get_response;
            }
            $proposal->additional_details = json_encode($proposal_additional_details);
            $proposal->additional_details_data = $proposal_additional_details;
            // $proposal->is_breakin_case = ($isBreakinInspectionRequired || ($is_breakin_case) ? 'Y' : 'N');
            $proposal->save();

            $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
            $data['ic_id'] = $productData->policy_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;

            updateJourneyStage($data);

            TataAigPremiumDetailController::saveV2PremiumDetails($premWebServiceId);

            if($is_breakin && $isBreakinInspectionRequired && $disable_breakin_ckyc != 'Y'){
                self::createTataBreakindata($proposal, $proposalResponse['ticket_number'], $disable_breakin_ckyc);
            }
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
                            if($is_breakin && $isBreakinInspectionRequired){
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
                            if($is_breakin && $isBreakinInspectionRequired){
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

                        if(!$validateCKYCJSON['status'])
                        {
                            return $validateCKYC;
                        }
                    }

                    if($isBreakinInspectionRequired || ($is_breakin_case == 'Y'))
                    {
                        if(!empty($validateCKYCJSON['data']['otp_id'] ?? '')){
                            $additionalDetailsData = $proposal->additional_details_data;

                            $additionalDetailsData['is_breakin_case'] = $is_breakin_case;
                            $additionalDetailsData['ticket_number'] = $proposalResponse['ticket_number'];

                            $new_proposal_data = [
                                'additional_details_data' => json_encode($additionalDetailsData)
                            ];
                            UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                                ->update($new_proposal_data);
                        }
                        else
                        {
                            self::createTataBreakindata($proposal, $proposalResponse['ticket_number'], $disable_breakin_ckyc);
                        }
                    }
                    return $validateCKYC;
                } catch(\Exception $e) {
                    return response()->json([
                        'status' => false,
                        'message' => $e->getMessage(),
                        'dev_msg' => 'Line No. : ' . $e->getLine(),
                    ]);
                }
            }
            //Handling CKYC Bypass for Breakin condition.
            if (config('constants.IS_CKYC_ENABLED_TATA_AIG') != 'Y' && !$is_liability) {
                if (($is_breakin && empty($proposalResponse['payment_id'])) || $isBreakinInspectionRequired) {
                    UserProposal::where(['user_proposal_id' => $proposal->user_proposal_id])
                        ->update([
                            'is_breakin_case' => 'Y',
                        ]);
                    $proposal->refresh();
                    self::createTataBreakindata($proposal, $proposalResponse['ticket_number'], $disable_breakin_ckyc);
                    $proposal->refresh();
                }
            }
            $submitProposalResponse = [
                'status' => true,
                'msg' => 'Proposal Submited Successfully..!',
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

    public static function checkTataAigMMV($productData, $version_id)
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

        $mmv = get_mmv_details($productData, $version_id, 'tata_aig_v2');

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

    public static function getToken($enquiryId, $productData, $transaction_type = 'proposal')
    {

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

        $tokenRequest = [
            'grant_type'    => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_GRANT_TYPE'),
            'scope'         => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_SCOPE'),
            'client_id'     => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_SECRET'),
        ];

        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_TOKEN'), $tokenRequest, 'tata_aig_v2', $additional_data);
        $tokenResponse = $get_response['response'];

        if($tokenResponse && $tokenResponse != '' && $tokenResponse != null)
        {
            $tokenResponse = json_decode($tokenResponse, true);

            if(!empty($tokenResponse))
            {
                if(isset($tokenResponse['error']))
                {
                    return [
                        'status'    => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg'       => $tokenResponse['error'],
                        'stage'     => 'token'
                    ];
                }
                else
                {
                    return [
                        'status'    => true,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'token'     => $tokenResponse['access_token'],
                        'stage'     => 'token'
                    ];
                }
            }
            else
            {
                return [
                    'status'    => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg'       => 'Insurer Not Reachable',
                    'stage'     => 'token'
                ];
            }
        }
        else
        {
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
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'No';
        $applicableAddon['DepreciationReimbursement'] = 'No';
        $applicableAddon['NoOfClaimsDepreciation'] = '0';
        $applicableAddon['ConsumablesExpenses'] = 'No';
        $applicableAddon['LossOfPersonalBelongings'] = 'No';
        $applicableAddon['EngineSecure'] = 'No';
        $applicableAddon['TyreSecure'] = 'No';
        $applicableAddon['KeyReplacement'] = 'No';
        $applicableAddon['RoadsideAssistance'] = 'No';
        $applicableAddon['ReturnToInvoice'] = 'No';
        $applicableAddon['NCBProtectionCover'] = 'No';
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'No';

        $applicableAddon['motorPlanOptedNo'] = '';
        switch ($masterProduct->product_identifier) {
            case 'SILVER':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'No';
                $applicableAddon['NoOfClaimsDepreciation'] = '0';
                $applicableAddon['ConsumablesExpenses'] = 'No';
                $applicableAddon['LossOfPersonalBelongings'] = 'No';
                $applicableAddon['EngineSecure'] = 'No';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'No';
                if(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_SILVER_PLAN_RSA_CHANGES') == 'Y')
                {   
                    $applicableAddon['RoadsideAssistance'] = 'No';
                }else
                {
                    $applicableAddon['RoadsideAssistance'] = 'Yes';
                }
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'No';

                $applicableAddon['motorPlanOptedNo'] = 'P1';

            break;

            case 'GOLD':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'No';
                $applicableAddon['NoOfClaimsDepreciation'] = '0';
                $applicableAddon['ConsumablesExpenses'] = 'No';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'No';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                $applicableAddon['motorPlanOptedNo'] = 'P2';

            break;

            case 'PEARL':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'No';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'No';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                $applicableAddon['motorPlanOptedNo'] = 'P3';

            break;

            case 'PEARL+':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'Yes';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'Yes';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                $applicableAddon['motorPlanOptedNo'] = 'P4';

            break;

            case 'SAPPHIRE':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'Yes';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'No';
                $applicableAddon['TyreSecure'] = 'Yes';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                $applicableAddon['motorPlanOptedNo'] = 'P5';

            break;

            case 'SAPPHIREPLUS':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'Yes';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'Yes';
                $applicableAddon['TyreSecure'] = 'Yes';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                $applicableAddon['motorPlanOptedNo'] = 'P6';

            break;

            case 'SAPPHIRE++':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'Yes';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'Yes';
                $applicableAddon['TyreSecure'] = 'Yes';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'Yes';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                $applicableAddon['motorPlanOptedNo'] = 'P7';

            break;

            case 'PLATINUM':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'No';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'Yes';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'Yes';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                // $applicableAddon['motorPlanOptedNo'] = 'P10';
                $applicableAddon['motorPlanOptedNo'] = 'P9';

            break;

            case 'CORAL':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'Yes';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'No';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                // $applicableAddon['motorPlanOptedNo'] = 'P11';
                $applicableAddon['motorPlanOptedNo'] = 'P10';

                if(env('APP_ENV') == 'local')
                {
                    $applicableAddon['NCBProtectionCover'] = 'No';
                }

            break;

            case 'PEARL++':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'Yes';
                $applicableAddon['DepreciationReimbursement'] = 'Yes';
                $applicableAddon['NoOfClaimsDepreciation'] = '2';
                $applicableAddon['ConsumablesExpenses'] = 'No';
                $applicableAddon['LossOfPersonalBelongings'] = 'Yes';
                $applicableAddon['EngineSecure'] = 'Yes';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'Yes';
                $applicableAddon['RoadsideAssistance'] = 'Yes';
                $applicableAddon['ReturnToInvoice'] = 'Yes';
                $applicableAddon['NCBProtectionCover'] = 'Yes';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'Yes';

                // $applicableAddon['motorPlanOptedNo'] = 'P12';
                $applicableAddon['motorPlanOptedNo'] = 'P11';

            break;

            case 'default':
                $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = 'No';
                $applicableAddon['DepreciationReimbursement'] = 'No';
                $applicableAddon['NoOfClaimsDepreciation'] = '0';
                $applicableAddon['ConsumablesExpenses'] = 'No';
                $applicableAddon['LossOfPersonalBelongings'] = 'No';
                $applicableAddon['EngineSecure'] = 'No';
                $applicableAddon['TyreSecure'] = 'No';
                $applicableAddon['KeyReplacement'] = 'No';
                $applicableAddon['RoadsideAssistance'] = 'No';
                $applicableAddon['ReturnToInvoice'] = 'No';
                $applicableAddon['NCBProtectionCover'] = 'No';
                $applicableAddon['EmergTrnsprtAndHotelExpense'] = 'No';
                $applicableAddon['motorPlanOptedNo'] = '';

            break;
        }
        return $applicableAddon;
    }

    public static function validaterequest($response)
    {
        if(!($response && $response != '' && $response != null))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
            ];
        }
        $response = json_decode($response, true);

        if(empty($response))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
            ];
        }

        if(isset($response['status']) && $response['status'] != 200)
        {
            return [
                'status'    => false,
                'msg'       => $response['message_txt'] ?? ($response['message'] ?? 'Insurer Not Reachable'),
            ];
        }
        else
        {
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

        if(isset($uat_model_master[$mmv->ic_version_code]))
        {
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

    public static function validateCKYC(UserProposal $proposalData, Array $proposalSubmitResponse, Array $webserviceData, $is_breakin_case)
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
            if(!empty($ckyc_response['data']['otp_id'] ?? '')) {
                return response()->json([
                    "status" => true,
                    "message" => "OTP Sent Successfully!",
                    "data" => [
                        "verification_status" => false,
                        "message" => "OTP Sent Successfully!",
                        'otp_id' => $ckyc_response['data']['otp_id'],
                        'is_breakin' => 'N',//$is_breakin_case,
                        'isBreakinCase' => 'N',//$is_breakin_case,
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

    public static function createTataBreakindata($proposalData, $ticketNumber, $disable_breakin_ckyc)
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
                'created_at'        => Carbon::today()->toDateString()
            ]
        ); 
        // updateJourneyStage([
        //     'user_product_journey_id' => $proposalData->user_product_journey_id,
        //     'ic_id' => $proposalData->ic_id,
        //     'stage' => STAGE_NAMES['INSPECTION_PENDING'],
        //     'proposal_id' => $proposalData->user_proposal_id
        // ]);
    }
}