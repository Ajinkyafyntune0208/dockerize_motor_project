<?php

namespace App\Jobs;

use App\Http\Controllers\KafkaController;
use stdClass;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\JourneyStage;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Bus\Queueable;
use App\Models\CvBreakinStatus;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentRequestResponse;
use Illuminate\Queue\SerializesModels;
use App\Models\FastlaneRequestResponse;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\PremiumDetails;
use App\Models\VahanServiceLogs;

class KafkaDataPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Summary.
     * Description
     * !!Configure below environment variables in the .env file before using Kafka Data push!!
     * 1. KAFKA_TOPIC
     * 2. KAFKA_KEYSTORE_LOCATION
     * 3. KAFKA_KEYSTORE_PASSWORD
     * 4. KAFKA_KEY_PASSWORD
     * @link https://github.com/mateusjunges/laravel-kafka
     */

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $enquiry_id;
    protected $stage;
    protected $source;
    protected $userLandedOnQuotePage;
    protected $clickedOnBuyNow;
    protected $isCkycInitiated;
    protected $premiumDetails;
    protected $premiumDetailsAvailable = false;

    public function __construct($enquiry_id, $stage, $source = 'RealTime')
    {
        $this->enquiry_id = $enquiry_id;
        $this->stage = $stage;
        $this->source = $source;
        $this->userLandedOnQuotePage = false;
        $this->clickedOnBuyNow = false;
        $this->isCkycInitiated = false;
        $this->premiumDetails = [];
        // if (app()->environment('local')) {
        if (true) {
            $details = PremiumDetails::select('details')->where('user_product_journey_id', $enquiry_id)->first();
            if (!empty($details->details ?? [])) {
                $this->premiumDetailsAvailable = true;
                $this->premiumDetails = $details->details;
            }
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('constants.motorConstant.KAFKA_DATA_PUSH_ENABLED') != 'Y') {
            return false;
        }
        if ($this->enquiry_id == 0) {
            Log::info('Enquiry ID is 0 Data push not executed as Stage : ' . $this->stage);
            return false;
        }

        //$journeyStageFunction = $this->stage . 'Data';

        // if (method_exists(__CLASS__, $journeyStageFunction) && is_callable(__CLASS__, $journeyStageFunction)) {
        $proposal = UserProposal::where('user_product_journey_id', (int) $this->enquiry_id)->get()->first();

        if (in_array($this->stage, ['LandedOnQuotePage', 'clickedOnBuyNow', 'ckycInitiated'])) {
            $this->userLandedOnQuotePage = $this->stage == 'LandedOnQuotePage';
            $this->clickedOnBuyNow = $this->stage == 'clickedOnBuyNow';
            $this->isCkycInitiated = $this->stage == 'ckycInitiated';
            $this->stage = $this->stage == 'LandedOnQuotePage' ? 'quote' : 'proposal';
            $proposalPayload = $this->proposalData($proposal);
            $customerPayload = $this->customerData($proposal);
            $vahanPayload = $this->vahanData($this->enquiry_id);
            $paymentPayload = $policyPayload = $inspectionPayload = false;
        } else if (!$proposal) {
            return false;
        } else {
            $proposalPayload = $this->proposalData($proposal);
            $customerPayload = $this->customerData($proposal);
            $paymentPayload = $this->paymentData($proposal, $this->stage == 'proposal');
            $policyPayload = $this->policyData($proposal);
            $inspectionPayload = $this->inspectionData($proposal);
            $vahanPayload = $this->vahanData($this->enquiry_id);
        }

        $enc_enquiry_id = customEncrypt($this->enquiry_id);
        $data = [
            "enquiry_id" => $enc_enquiry_id,
            "proposal" => $proposalPayload === false ? (object) [] : $proposalPayload,
            "customer" => $customerPayload === false ? (object) [] : $customerPayload,
            "payment" => $paymentPayload === false ? (object) [] : $paymentPayload,
            "policy" => $policyPayload === false ? (object) [] : $policyPayload,
            "inspection" => $inspectionPayload === false ? (object) [] : $inspectionPayload,
        ];

        if (config('constants.motorConstant.IS_VAHAN_DATA_PUSH_ENABLED') == 'Y') {
            foreach ($vahanPayload as $key => $vahan) {
                $data[$key] = $vahan;
            }
        }

        if (!$data) {
            return false;
        }

        $data = [
            "ft_track_id" => $enc_enquiry_id,
            "type" => strtolower($this->stage),
            "source_id" => "FT",
            "data" => $data,
        ];

        if ($this->userLandedOnQuotePage || $this->clickedOnBuyNow) {
            $data['data']['stage'] = $this->userLandedOnQuotePage ? 'quote' : 'proposal';
        }else
        {
            $data['data']['stage'] = (strtolower($this->stage) == 'policy') ? 'payment' : $this->stage; 
            if(!empty($paymentPayload))
            {
                $data['data']['stage'] = 'payment';
            }
        }

        // If Validation Fails, then email will be sent.
        (new KafkaController())->ValidateKafkaPayload($this->enquiry_id, $data['data']);

        // $this->createLog($data);
        $message = new Message(body:$data);
        $this->pushData($message);

        $this->createLog($data); // Need to maintain log after data push is done - 22-08-2023

        // } else {
        //     Log::alert('Kafka Data Push Method not found! - Method name : ' . $journeyStageFunction);
        // }
        return true;
    }
    /**
     * Get Custom Message from stage based
     * @return array
     */
    public function getCustomMessage()
    {
        $proposal = UserProposal::where('user_product_journey_id', (int) $this->enquiry_id)->get()->first();
        if (!$proposal) {
            return false;
        }
        $journeyStageFunction = $this->stage . 'Data';
        return $this->{$journeyStageFunction}($proposal);
    }

    /** Prepare Customer Data
     * return array()
     */
    protected function customerData(UserProposal $proposalDetails = null)
    {
        //Need to pass proposal data if userLandedOnQuotePage, clickedOnBuyNow or isCkycInitiated
        if (!$proposalDetails && !$this->userLandedOnQuotePage && !$this->clickedOnBuyNow && !$this->isCkycInitiated) {
            return false;
        }
        $product_sub_type_id = UserProductJourney::where('user_product_journey_id', $this->enquiry_id)->pluck('product_sub_type_id')->first();
        $insurance_type = '';
        if ($product_sub_type_id && $product_sub_type_id > 0) {
            $insurance_type = ucwords(get_parent_code($product_sub_type_id));
        }

        $comm_city_id = DB::table('fyntune_city_master')->where('city_name', $proposalDetails->city ?? '')->pluck('rb_code')->first();
        $comm_city_id = empty($comm_city_id) ? null : (int) $comm_city_id;
        $salutation = $gender = null;
        if (($proposalDetails->owner_type ?? '') == 'I') {
            if (in_array(strtolower($proposalDetails->gender ?? ''), ['m', 'male'])) {
                $salutation = "Mr.";
                $gender = "MALE";
            } elseif (in_array(strtolower($proposalDetails->gender ?? ''), ['f', 'female'])) {
                $gender = "FEMALE";
                if (($proposalDetails->marital_status ?? '') == "Single") {
                    $salutation = "Mrs.";
                } else {
                    $salutation = "Miss.";
                }
            }
        } else {
            $salutation = "M/S.";
        }
        $quoteLog = QuoteLog::where('user_product_journey_id', $this->enquiry_id)->first();
        $company_alias = $quoteLog->premium_json['company_alias'] ?? null;
        $nominee_relationship = null;
        if (!empty($proposalDetails->nominee_relationship ?? '')) {
            $nominee_relationship = DB::table('nominee_relationship')->where([
                'company_alias' => $company_alias,
                'relation_code' => $proposalDetails->nominee_relationship,
            ])->pluck('relation')->first();
        }
        return [
            "aadhar_number" => "",
            "active" => true,
            "communication_addrline1" => $proposalDetails->address_line1 ?? '',
            "communication_addrline2" => $proposalDetails->address_line2 ?? '',
            "communication_addrline3" => $proposalDetails->address_line3 ?? '',
            "communication_city" => $comm_city_id,
            "communication_email" => $proposalDetails->email ?? '',
            "communication_mobileno" => $proposalDetails->mobile_number ?? '',
            "communication_name" => $proposalDetails->first_name ?? '',
            "communication_pincode" => empty($proposalDetails->pincode ?? '') ? null : (int) $proposalDetails->pincode,
            "communication_state" => $proposalDetails->state ?? '',
            "creation_time" => $proposalDetails->created_date ?? (string) now(),
            "customertype" => ($proposalDetails->owner_type ?? '') == 'C' ? 'Corporate' : 'Individual', //Updating 'Company' to 'Corporate' Jira Ticket ID:189
            "dob" => !empty($proposalDetails->dob ?? '') ? date('Y-m-d', strtotime($proposalDetails->dob)) : null,
            "driving_license" => null,
            "email" => $proposalDetails->email ?? '',
            "firstname" => $proposalDetails->first_name ?? '',
            "gender" => $gender,
            "gstin_number" => $proposalDetails->gst_number ?? null,
            "inspection_addrline1" => null,
            "inspection_loc" => null,
            "inspection_pincode" => null,
            "insurance_type" => $insurance_type,
            "ip_address" => null,
            "lastname" => $proposalDetails->last_name ?? '',
            "libertyVideoconCustomerId" => null,
            "maritalStatus" => ($proposalDetails->owner_type ?? '') == 'I' ? ($proposalDetails->marital_status ?? null) : null,
            "mobileno" => empty($proposalDetails->mobile_number ?? '') ? null : (int) $proposalDetails->mobile_number,
            "nomineeAge" => $proposalDetails->nominee_age ?? null,
            "nomineedob" => !empty($proposalDetails->nominee_dob ?? '') ? date('Y-m-d', strtotime($proposalDetails->nominee_dob)) : null,
            "nomineeName" => $proposalDetails->nominee_name ?? null,
            "nomineeRelation" => empty($nominee_relationship) ? ($proposalDetails->nominee_relationship ?? '') : $nominee_relationship,
            "occupation_id" => null, //$proposalDetails->occupation ?? null,
            "otp_number" => null,
            "panNumber" => $proposalDetails->pan_number ?? null,
            "passportno" => null,
            "registration_addrline1" => ($proposalDetails->is_car_registration_address_same ?? 0) == 1 ? $proposalDetails->address_line1 : $proposalDetails->car_registration_address1 ?? '',
            "registration_addrline2" => ($proposalDetails->is_car_registration_address_same ?? 0) == 1 ? $proposalDetails->address_line2 : $proposalDetails->car_registration_address2 ?? '',
            "registration_addrline3" => ($proposalDetails->is_car_registration_address_same ?? 0) == 1 ? $proposalDetails->address_line3 : $proposalDetails->car_registration_address3 ?? '',
            "registration_city" => ($proposalDetails->is_car_registration_address_same ?? 0) == 1 ? $comm_city_id : DB::table('fyntune_city_master')->where('city_name', $proposalDetails->car_registration_city ?? '')->pluck('rb_code')->first(),
            "registration_pincode" => $proposalDetails->pincode ?? '',
            "registration_state" => $proposalDetails->state ?? '',
            "salutation" => $salutation,
            "voter_id" => null,
        ];
    }

    /** Prepare Proposal Data
     * return array()
     */
    protected function proposalData(UserProposal $proposalDetails = null)
    {
        $user_data = UserProductJourney::with([
            'corporate_vehicles_quote_request',
            'agent_details',
            'quote_log',
            'addons',
        ])->where('user_product_journey_id', $this->enquiry_id)->first();

        if (!$user_data && !$this->userLandedOnQuotePage && !$this->clickedOnBuyNow && !$this->isCkycInitiated) {
            return false;
        }
        $corporateData = $user_data->corporate_vehicles_quote_request;
        if (!$corporateData) {
            return false;
        }
        $productData = new stdClass();
        $productData->product_sub_type_id = $user_data->product_sub_type_id;
        $variant_id = get_mmv_details($productData, $corporateData->version_id, 'renewbuy');
        $rb_variant_id = null;
        if ($variant_id['status']) {
            $rb_variant_id = (int) $variant_id['data'];
        }
        $agent_details = $user_data->agent_details->toArray();

        $company_alias = $user_data->quote_log->premium_json['company_alias'] ?? null;

        $pd_data_allowed = KafkaController::considerDataFromPremiumDetails($user_data->product_sub_type_id, $company_alias ?? '');
        if (!$pd_data_allowed) {
            $this->premiumDetailsAvailable = false;
            $this->premiumDetails = [];
        }
        
        $previous_ic_full_name = $previous_insurer_id = null;
        if ($corporateData->business_type != 'newbusiness' && $company_alias) {
            $previous_ic_full_name = PreviousInsurerList::where([
                'code' => $proposalDetails->previous_insurance_company ?? '',
                'company_alias' => $company_alias,
            ])->pluck('name')->first();
            $previous_insurer_id = DB::table('fyntune_previous_insurer_master')->whereRaw('? LIKE CONCAT("%", identifier, "%")', $previous_ic_full_name ?? '')->pluck('rb_code')->first();
        }

        if($this->stage === 'quote' && $corporateData->business_type != 'newbusiness' && $corporateData->previous_policy_type != 'Not sure')
        {
            $previous_insurer_id = DB::table('fyntune_previous_insurer_master')->whereRaw('? LIKE CONCAT("%", identifier, "%")', $corporateData->previous_insurer ?? '')->pluck('rb_code')->first();
        }

        $userSelectedIC = $proposalDetails->ic_name ?? '';
        if ($this->clickedOnBuyNow) {
            $userSelectedIC = $user_data->quote_log->ic_alias ?? '';
        }
        $dummy_insurer_id = env('APP_ENV') == 'local' ? 76 : 6; // RB Bajaj code.
        $insurer_details = DB::table('fyntune_previous_insurer_master')->whereRaw('? LIKE CONCAT("%", identifier, "%")', $userSelectedIC)->select('rb_code', 'rb_policy_insurer')->first();
        $insurer_id = $this->userLandedOnQuotePage ? $dummy_insurer_id : ($insurer_details->rb_code ?? null);
        $policy_insurer = $insurer_details->rb_policy_insurer ?? null;

        $premium_json = $user_data->quote_log->premium_json ?? [];
        $selected_addons = $user_data->addons[0]->applicable_addons ?? [];
        $cpa = $user_data->addons[0]->compulsory_personal_accident ?? [];
        $accessories = $user_data->addons[0]->accessories ?? [];
        $discounts = $user_data->addons[0]->discounts ?? [];
        $additional_covers = $user_data->addons[0]->additional_covers ?? [];

        // Define all addon boolean value
        $is_zero_dept = $is_rsa = $is_consumable = $is_eme_cover = $is_key_replacement = $is_engine_prot = $is_ncb_protection = $is_tyre_secure = $is_rti = $is_personal_belongings = false;
        $is_geographical_extension = false;

        // Define all addons, accessories, cpa premium
        $consumable_premium = $engine_protector_premium = $key_replacement_premium = $rsa_premium = $rti_premium = $tyre_secure_premium = $zero_dep_premium = $personal_belongings_premium = $ncb_protection_premium = $pa_owner_driver_premium = $electrical_accessories = $electrical_accessories_premium = $non_electrical_accessories = $non_electrical_accessories_premium = $lpg_cng_SI = $lpg_cng_premium = $cpa_term_year = $voluntary_deductible_value = $voluntary_deductible_premium = $emergecy_medical_expense_premium = 0.0;
        $geographical_extension_premium = $geographical_extension_tppremium = 0;
        if (count($premium_json) != 0) {
            list($zero_dep_premium, $is_zero_dept) = $this->getAddonValue('zeroDepreciation', $premium_json, $selected_addons, 'Zero Depreciation');
            list($rsa_premium, $is_rsa) = $this->getAddonValue('roadSideAssistance', $premium_json, $selected_addons, 'Road Side Assistance');
            list($consumable_premium, $is_consumable) = $this->getAddonValue('consumables', $premium_json, $selected_addons, 'Consumable');
            list($key_replacement_premium, $is_key_replacement) = $this->getAddonValue('keyReplace', $premium_json, $selected_addons, 'Key Replacement');
            list($engine_protector_premium, $is_engine_prot) = $this->getAddonValue('engineProtector', $premium_json, $selected_addons, 'Engine Protector');
            list($ncb_protection_premium, $is_ncb_protection) = $this->getAddonValue('ncbProtection', $premium_json, $selected_addons, 'NCB Protection');
            list($tyre_secure_premium, $is_tyre_secure) = $this->getAddonValue('tyreSecure', $premium_json, $selected_addons, 'Tyre Secure');
            list($rti_premium, $is_rti) = $this->getAddonValue('returnToInvoice', $premium_json, $selected_addons, 'Return To Invoice');
            list($personal_belongings_premium, $is_personal_belongings) = $this->getAddonValue('lopb', $premium_json, $selected_addons, 'Loss of Personal Belongings');
            list($emergecy_medical_expense_premium, $is_eme_cover) = $this->getAddonValue('emergencyMedicalExpenses', $premium_json, $selected_addons, 'Emergency Medical Expenses');
            if (count($cpa) > 0 && ($proposalDetails->owner_type ?? '') == 'I') {
                $pa_owner_driver_premium = $this->getCpaValue($premium_json, $cpa);
                $cpa_term_year = 1;
                if (in_array((int) $user_data->product_sub_type_id, [1, 2]) && $pa_owner_driver_premium > 500) {
                    $cpa_term_year = ($user_data->product_sub_type_id == 1) ? 3 : 5;
                }
            }
            if (count($accessories) > 0) {
                list($electrical_accessories, $electrical_accessories_premium) = $this->getAccessoriesValue('motorElectricAccessoriesValue', $premium_json, $accessories, 'Electrical Accessories');

                list($non_electrical_accessories, $non_electrical_accessories_premium) = $this->getAccessoriesValue('motorNonElectricAccessoriesValue', $premium_json, $accessories, 'Non-Electrical Accessories');

                list($lpg_cng_SI, $lpg_cng_premium) = $this->getAccessoriesValue('motorLpgCngKitValue', $premium_json, $accessories, 'External Bi-Fuel Kit CNG/LPG');
            }
            if (count($discounts)) {
                list($voluntary_deductible_value, $voluntary_deductible_premium) = $this->getDiscountValue('voluntaryExcess', $premium_json, $discounts, 'voluntary_insurer_discounts');
            }

        }
        $rto_id = DB::table('fyntune_rto_master')->where([
            ['rb_code', '!=', 'NA'],
        ])
        ->whereIn('rto_code', [$corporateData->rto_code, RtoCodeWithOrWithoutZero($corporateData->rto_code, true)])
        ->pluck('rb_code')->first();

        $financier_code = $financier_name = null;
        $underloan = '0';
        $additional_details = json_decode($proposalDetails->additional_details ?? '[]');
        if (isset($additional_details->vehicle->isVehicleFinance) && $additional_details->vehicle->isVehicleFinance) {
            $underloan = '1';
            $financier_name = $additional_details->vehicle->financer_name ?? null;
            $financier_code = $additional_details->vehicle->nameOfFinancer ?? null;
        }
        $policy_startdate_tp = !empty($proposalDetails->tp_start_date) ? date('Y-m-d', strtotime($proposalDetails->tp_start_date ?? '')) : null;
        $policy_enddate_tp = !empty($proposalDetails->tp_end_date) ? date('Y-m-d', strtotime($proposalDetails->tp_end_date)) : null;
        $term_year = $term_year_tp = 1;

        if ($corporateData->policy_type == 'third_party' || $corporateData->policy_type == 'third_party_breakin') {
            $term_year = 0;
        }

        $product_term_year_tp = [
            1 => 3, // New business Car TP term year is 3 years
            2 => 5, // New business Bike TP term year is 5 years
        ];
        if ($corporateData->business_type == 'newbusiness') {
            $term_year_tp = $product_term_year_tp[(int) $user_data->product_sub_type_id] ?? 1;
            //Third-Party Policy State/End Date
            $policy_startdate_tp = !empty($proposalDetails->policy_start_date) ? date('Y-m-d', strtotime($proposalDetails->policy_start_date ?? '')) : null;
            $year = $product_term_year_tp[(int) $user_data->product_sub_type_id] ?? 1;
            $policy_enddate_tp = !empty($policy_startdate_tp) ? date('Y-m-d', strtotime('+' . $year . ' Year -1 Day', strtotime($policy_startdate_tp))) : null;
        } elseif ($corporateData->policy_type == 'own_damage') {
            $term_year_tp = 0;
            $policy_startdate_tp = $policy_enddate_tp = null;
        } elseif ($corporateData->policy_type == 'comprehensive') {
            $policy_startdate_tp = !empty($proposalDetails->policy_start_date) ? date('Y-m-d', strtotime($proposalDetails->policy_start_date ?? '')) : null;
            $policy_enddate_tp = !empty($proposalDetails->policy_end_date) ? date('Y-m-d', strtotime($proposalDetails->policy_end_date ?? '')) : null;
        }
        $tppd_premium = 0;
        if (isset($premium_json['tppdDiscount']) && $premium_json['tppdDiscount'] > 0) {
            $tppd_premium = (int) $premium_json['tppdDiscount'];
        }
        $pa_unnamed_passenger = 0;
        if (isset($premium_json['mmvDetail']['seatingCapacity']) && (int) $premium_json['mmvDetail']['seatingCapacity'] > 0) {
            $pa_unnamed_passenger = (int) $premium_json['mmvDetail']['seatingCapacity'];
        }

        $pa_unnamed_passenger_si = 0;
        if (count($additional_covers)) {
            list($pa_unnamed_passenger_si) = $this->getAdditionalCoversValue('coverUnnamedPassengerValue', $premium_json, $additional_covers, 'Unnamed Passenger PA Cover');
            list($is_geographical_extension, $geographical_extension_premium) = $this->getAdditionalCoversValue('geogExtensionODPremium', $premium_json, $additional_covers, 'Geographical Extension');
            list($is_geographical_extension, $geographical_extension_tppremium) = $this->getAdditionalCoversValue('geogExtensionTPPremium', $premium_json, $additional_covers, 'Geographical Extension');
        }
        
        $anti_theft_premium = ($premium_json['antitheftDiscount'] ?? 0);
        $lpg_cng_tp_premium = (int) ($premium_json['cngLpgTp'] ?? 0);
        $ncb_discount = (float) ($premium_json['deductionOfNcb'] ?? 0);
        $odpremium = (float) $user_data->quote_log->od_premium;
        $passengerAssistCover = (float) ($premium_json['addOnsData']['other']['passengerAssistCover'] ?? 0); // It is available with Zero-Dept in Liberty
        $accidentShield = $conveyanceBenefit = 0;
        if (
            isset($premium_json['productIdentifier'])
            && in_array($premium_json['productIdentifier'], ['TELEMATICS_PREMIUM', 'TELEMATICS_PRESTIGE', 'TELEMATICS_CLASSIC'])
        ) {
            $accidentShield = (float) ($premium_json['addOnsData']['other']['accidentShield'] ?? 0); // It is available with Consumable in Bajaj Car
            $conveyanceBenefit = (float) ($premium_json['addOnsData']['other']['conveyanceBenefit'] ?? 0); // It is available with Consumable in Bajaj Car
        }
        $other_discount = $premium_json['icVehicleDiscount'] ?? 0;
        $loading_amount = 0;
        if (isset($premium_json['totalLoadingAmount']) && ($premium_json['totalLoadingAmount']) > 0) {
            $loading_amount = (float) $premium_json['totalLoadingAmount'];
        } else if (isset($premium_json['underwritingLoadingAmount']) && ($premium_json['underwritingLoadingAmount']) > 0) {
            $loading_amount = (float) $premium_json['underwritingLoadingAmount'];
        }
        $odpremium = (float) (
            $odpremium
             - $voluntary_deductible_premium
             - $anti_theft_premium
             - $other_discount
             - $ncb_discount
             + $loading_amount
             + ($zero_dep_premium > 0 ? $passengerAssistCover : 0)
             + $accidentShield
             + $conveyanceBenefit
        );
        $tppremium = (int) ($premium_json['tppdPremiumAmount'] ?? 0) - $tppd_premium + $lpg_cng_tp_premium;

        //Coverage Type
        $master_policy_id = $user_data->quote_log->master_policy_id;
        $coverage_type = 0;
        $all_coverages = [
            "comprehensive" => 0,
            "third_party" => 1,
            "own_damage" => 2,
            "breakin" => 0, // Comprehensive Breakin
            "short_term_3" => null,
            "own_damage_breakin" => 2,
            "third_party_breakin" => 1,
            "short_term_6" => null,
            "short_term_3_breakin" => null,
            "short_term_6_breakin" => null,
            "RENEWAL" => null,
        ];
        $ncb = (int) $corporateData->applicable_ncb;
        $premium_type_code = MasterPolicy::where('policy_id', $master_policy_id)->
            join('master_premium_type as m', 'm.id', '=', 'master_policy.premium_type_id')
            ->select('m.premium_type_code')->get()->first();
        if (isset($premium_type_code->premium_type_code) && !empty($premium_type_code)) {
            //Do not pass OD Policy start date and end date in case of Third-Party
            if (in_array($premium_type_code->premium_type_code, ['third_party', 'third_party_breakin'])) {
                if ($proposalDetails) {
                    $proposalDetails->policy_start_date = null;
                    $proposalDetails->policy_end_date = null;
                }
                $ncb = $term_year = 0;
            }
            $coverage_type = (!empty($premium_type_code->premium_type_code) && isset($all_coverages[$premium_type_code->premium_type_code])) ? $all_coverages[$premium_type_code->premium_type_code] : null;
        }
        $fuel_type = (
            isset($premium_json['mmvDetail']['fuelType']) &&
            !empty($premium_json['mmvDetail']['fuelType']))
        ? $premium_json['mmvDetail']['fuelType']
        : ($premium_json['fuelType'] ?? null);

        $profession_name = $proposalDetails->occupation_name ?? '';
        // if (!empty($proposalDetails->occupation)) {
        //     $profession_name = MasterOccupation::where([
        //         'company_alias' => $company_alias,
        //         'occupation_code' => $proposalDetails->occupation,
        //     ])->pluck('occupation_name')->first();
        // }
        $has_previous_policy = !($corporateData->business_type == 'newbusiness' || strtolower($corporateData->previous_policy_type) == 'not sure');

        if ($has_previous_policy) {
            if (in_array($premium_type_code->premium_type_code ?? '', ['own_damage', 'own_damage_breakin']) && isset($additional_details->prepolicy->tpStartDate)) {
                $previous_policy_startdate_tp = date('Y-m-d', strtotime($additional_details->prepolicy->tpStartDate));
                $previous_policy_enddate_tp = date('Y-m-d', strtotime($additional_details->prepolicy->tpEndDate));
            } else {
                $manf_year = date('Y', strtotime('01-' . ($proposalDetails->vehicle_manf_year ?? '')));
                $expiry_year = date('Y', strtotime($corporateData->previous_policy_expiry_date));
                $year_diff = $expiry_year - $manf_year;
                if ($user_data->product_sub_type_id == 1 && $year_diff == 3) {
                    $previous_policy_startdate_tp = date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date . ' -3 Year +1 Day'));
                } else if ($user_data->product_sub_type_id == 2 && $year_diff == 5) {
                    $previous_policy_startdate_tp = date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date . ' -5 Year +1 Day'));
                } else {
                    $previous_policy_startdate_tp = date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date . ' -1 Year +1 Day'));
                }
                $previous_policy_enddate_tp = date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date));
            }
        } else {
            $previous_policy_startdate_tp = null;
            $previous_policy_enddate_tp = null;
        }

        if(empty($proposalDetails->policy_start_date)  && empty($proposalDetails->policy_end_date))
        {
            // DUMMY dates needs to be passed if not vailable as per jira ID 896
            if($corporateData->business_type == 'newbusiness')
            {
                $policy_start_date = date('Y-m-d');
                $policy_end_date = date('Y-m-d', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            }else
            {
                $policy_start_date = date('Y-m-d');
                $policy_end_date = date('Y-m-d', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                //Do not pass OD Policy start date and end date in case of Third-Party
                if (in_array($premium_type_code->premium_type_code ?? '', ['third_party', 'third_party_breakin'])) {
                    if ($proposalDetails) {
                        $policy_start_date = null;
                        $policy_end_date = null;
                    }
                }
            }
        }else
        {
            $policy_start_date = empty($proposalDetails->policy_start_date ?? '') ? null : date('Y-m-d', strtotime($proposalDetails->policy_start_date ?? ''));
            $policy_end_date = empty($proposalDetails->policy_end_date ?? '') ? null : date('Y-m-d', strtotime($proposalDetails->policy_end_date ?? ''));
        }

        if ($corporateData->business_type == 'newbusiness' && !empty($proposalDetails->policy_start_date)) {
            $policy_start_date = date('Y-m-d', strtotime($proposalDetails->policy_start_date));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        if (in_array(($premium_type_code->premium_type_code ?? ''), ['third_party', 'third_party_breakin'])) {
            $policy_start_date = null;
            $policy_end_date = null;
        }

        $journeyStage = JourneyStage::where('user_product_journey_id', $this->enquiry_id)->select('proposal_url', 'quote_url')->first();

 
        if(!empty($agent_details) && count($agent_details) > 1)
        {
            foreach($agent_details as $agent_key => $agent_value)
            {
                if($agent_value['seller_type'] == 'P')
                {
                    $agent_details[0] = $agent_value;
                }
            }
        }

        $is_fetched_from_parivahan = false;
        $is_parivahan_verified = false;
        if($corporateData->vehicle_registration_no !== null and strtolower($corporateData->vehicle_registration_no) !== 'new')
        {
            $input_data = FastlaneRequestResponse::select('response')
                                                    ->where('request' ,$corporateData->vehicle_registration_no)
                                                    ->where('transaction_type','Ongrid Service')
                                                    ->first();
            if($input_data !== null)
            {
                $response_data = json_decode($input_data->response);
                if(isset($response_data->status) && $response_data->status == 200)
                {
                    $is_fetched_from_parivahan = true;
                }
            }

            $proposal_data_input = \App\Models\ProposalVehicleValidationLogs::select('response','service_type')
                                                            ->where('vehicle_reg_no' ,$corporateData->vehicle_registration_no)
                                                            ->first();
            
            if($proposal_data_input !== null)
            {
                $response_data_proposal = json_decode($proposal_data_input->response);
                if(isset($response_data_proposal->status) && $response_data_proposal->status == 100 && ($proposal_data_input->service_type == 'fastlane'))
                {
                    $is_parivahan_verified = true;
                }else if(isset($response_data_proposal->status) && $response_data_proposal->status == 200 && ($proposal_data_input->service_type == 'ongrid'))
                {
                    $is_parivahan_verified = true;  
                }
            }
                                                            
            
        }
        $vehicle_idv = 0;
        $premium_payable = 0;
        $servicetax = 0;

        if(empty($proposalDetails))
        {
            
            $vehicle_idv = (float) ($user_data->quote_log->idv ?? 0);
            $premium_payable = (float) ($user_data->quote_log->final_premium_amount ?? 0);
            $servicetax = (float) ($user_data->quote_log->service_tax ?? 0);

        }else
        {
            $vehicle_idv = (float) ($proposalDetails->idv ?? 0);
            $premium_payable = ($proposalDetails->final_payable_amount ?? 0);
            $servicetax = (float) ($proposalDetails->service_tax_amount ?? 0);
            
        }

        $ll_paid_driver = (int) ($premium_json['defaultPaidDriver'] ?? 0);
        $ll_paid_employee = (int) ($premium_json['otherCovers']['legalLiabilityToEmployee'] ?? 0);
        $pa_additional_driver = (int) ($premium_json['motorAdditionalPaidDriver'] ?? 0);
        $pa_unnamed_passenger_premium = (int) ($premium_json['coverUnnamedPassengerValue'] ?? 0);
        if ($this->premiumDetailsAvailable) {
            $anti_theft_premium = $this->premiumDetails['anti_theft'];
            $ncb_discount = $this->premiumDetails['ncb_discount_premium'];
            $other_discount = $this->premiumDetails['other_discount'];
            $tppd_premium = $this->premiumDetails['tppd_discount'];
            $loading_amount = $this->premiumDetails['loading_amount'];
            $odpremium = 
                $this->premiumDetails['od_premium']
                - $voluntary_deductible_premium
                - $anti_theft_premium
                - $other_discount
                - $ncb_discount
                + $loading_amount
                + ($zero_dep_premium > 0 ? $this->premiumDetails['passenger_assist_cover'] : 0)
                + $this->premiumDetails['accident_shield']
                + $this->premiumDetails['conveyance_benefit'];
            $lpg_cng_tp_premium = $this->premiumDetails['bifuel_tp_premium'];
            $premium_payable = $this->premiumDetails['final_payable_amount'];
            $servicetax = $this->premiumDetails['service_tax_amount'];
            $tppremium = $this->premiumDetails['basic_tp_premium'] - $tppd_premium + $lpg_cng_tp_premium;
            $pa_unnamed_passenger_premium = $this->premiumDetails['unnamed_passenger_pa_cover'];
            $ll_paid_driver = $this->premiumDetails['ll_paid_driver'];
            $ll_paid_employee = $this->premiumDetails['ll_paid_employee'] ?? 0.0;
            $pa_additional_driver = $this->premiumDetails['pa_additional_driver'];
        }

        if (in_array(($premium_json['company_alias'] ?? ''), ['united_india'])) {
            $odpremium+= $ncb_discount;
        }

        if (in_array(($premium_json['company_alias'] ?? ''), ['oriental'])) {
            $odpremium+= round($engine_protector_premium, 2) + round($personal_belongings_premium, 2);
        }

        
        if (
            empty($policy_startdate_tp) &&
            !empty($proposalDetails->tp_start_date) &&
            !empty($proposalDetails->tp_end_date) &&
            in_array($premium_type_code->premium_type_code ?? '', ['third_party', 'third_party_breakin'])
        ) {
            $policy_startdate_tp =  date('Y-m-d', strtotime($proposalDetails->tp_start_date));
            $policy_enddate_tp = date('Y-m-d', strtotime($proposalDetails->tp_end_date ?? ''));
        }
        
        return [
            "ft_quote_url" => $journeyStage->quote_url ?? "",
            "ft_proposal_url" => ($this->stage == 'quote') ? null : (!empty($journeyStage->proposal_url) ? str_replace("quotes","proposal-page",$journeyStage->proposal_url) : ""),
            "aa_membership" => false,
            "accidential_cover" => null,
            "accidential_cover_premium" => 0.0,
            "actual_commission" => null,
            "additional_towing_premium" => 0.0,
            "age" => empty($proposalDetails->dob ?? '') ? null : date('Y', strtotime($proposalDetails->created_date ?? '')) - date('Y', strtotime($proposalDetails->dob ?? '')),
            "anti_theft_device_discount_premium" => 0.0,
            "anti_theft_discount_premium" => round(($anti_theft_premium * (-1)), 2),
            "arai" => false,
            "automobile_associate_discount_premium" => 0.0,
            "chassis_no" => $proposalDetails->chassis_number ?? '',
            "ckyc_status" => empty($proposalDetails->is_ckyc_verified ?? '') ? null : ($proposalDetails->is_ckyc_verified == 'Y' ? 'approved' : 'pending'),
            "claim_previousyear" => $corporateData->is_claim == 'Y' ? true : false,
            "consumable_premium" => $consumable_premium,
            "courtesy_car_premium" => 0.0,
            "coverage_type" => $coverage_type,
            "cpa_term_year" => $pa_owner_driver_premium > 0 ? $cpa_term_year : null,
            "daily_allowance_premium" => 0.0,
            "daily_cash_premium" => 0.0,
            "created_at" => $proposalDetails->created_date ?? (string) now(),
            "deduction_of_ncb_premium" => round(($ncb_discount * (-1)), 2),
            "electrical_accessories" => $electrical_accessories,
            "electrical_accessories_premium" => round($electrical_accessories_premium, 2),
            "emergecy_medical_expense_premium" => $is_eme_cover ? round($emergecy_medical_expense_premium, 2) : 0.0,
            "emi_cover_premium" => 0.0,
            "engine_no" => $proposalDetails->engine_number ?? '',
            "engine_protector_premium" => round($engine_protector_premium, 2),
            "executive_id" => $agent_details[0]['user_name'] ?? null,
            "expected_commission" => null,
            "exshowroomPrice" => null,
            "financier_code" => $financier_code,
            "financier_name" => $financier_name,
            "fuel_adulteration_premium" => 0.0,
            "fuel_type" => $fuel_type,
            "generatedby_type" => 6, # Use 6 for fyntune
            "generation_time" => date('Y-m-d H:i:s', strtotime($proposalDetails->created_date ?? (string) now())),
            "geographical_extension_premium" => round($geographical_extension_premium, 2),
            "geographical_extension_tppremium" => round($geographical_extension_tppremium, 2),
            "handicap_discount_premium" => 0.0,
            "has_prev_policy" => $has_previous_policy,
            "helmet_cover_premium" => 0.0,
            "hospi_cash_premium" => 0.0,
            "hotel_expenses_premium" => 0.0,
            "imported_vehicles_premium" => 0.0,
            "inconvenience_allowance_premium" => 0.0,
            "insurer_id" => empty($insurer_id) ? null : (int) $insurer_id,
            "is_active_quotes" => false,
            "is_additional_towing" => false,
            "is_anti_theft_attached" => ($anti_theft_premium) > 0,
            "is_anti_theft_device_discount" => false,
            "is_automobile_associate_discount" => false,
            "is_break_in" => $corporateData->business_type == 'breakin',
            "is_consumable" => $is_consumable,
            "is_courtesy_car" => false,
            "is_daily_allowance" => false,
            "is_daily_cash" => false,
            "is_discount" => false,
            "is_electrical_accessories" => $electrical_accessories > 0,
            "is_emergency_medical_expenses" => $is_eme_cover,
            "is_emi_cover" => false,
            "is_engine_protector" => $is_engine_prot,
            "is_fuel_adulteration" => false,
            "is_fetched_from_parivahan" => $is_fetched_from_parivahan,
            "is_generated_by_ria" => false,
            "is_geographical_extension" => ($geographical_extension_premium + $geographical_extension_tppremium) > 0,
            "is_helmet_cover" => false,
            "is_hospi_cash" => false,
            "is_hotel_expenses_covered" => false,
            "is_imported_vehicles" => false,
            "is_inconvenience_allowance" => false,
            "is_key_replacement" => $is_key_replacement,
            "is_ll_employee" => ($ll_paid_employee ?? 0) > 0,
            "is_ll_paid_driver" => $ll_paid_driver > 0,
            "is_lost_renewal" => false,
            "is_lpg_cng" => ($lpg_cng_SI > 0 || $lpg_cng_tp_premium > 0),
            "is_ncb_carrying_forward" => false,
            "is_ncb_protection" => $is_ncb_protection,
            "is_new" => $corporateData->business_type == 'newbusiness',
            "is_non_electrical_accessories" => $non_electrical_accessories > 0,
            "is_nonelectrical_accessories" => $non_electrical_accessories > 0,
            "is_ownership_transfer" => false,
            "is_pa_owner_driver" => $pa_owner_driver_premium > 0,
            "is_pa_paid_driver" => $pa_additional_driver > 0,
            "pa_paid_driver_premium" => $pa_additional_driver,
            "is_pa_unnamed_passenger" => $pa_unnamed_passenger_premium > 0,
            "is_parivahan_verified" => $is_parivahan_verified,
            "is_personal_belonging_covered" => $is_personal_belongings,
            "is_prev_zero_dep" => $corporateData->business_type == 'newbusiness' ? false : ($zero_dep_premium > 0),
            "is_repair_glass_fibre" => false,
            "is_rodent_bite_cover" => false,
            "is_rsa" => $is_rsa,
            "is_rti" => $is_rti,
            "is_self_renewal" => $corporateData->is_renewal == 'Y',
            "is_towing_cover" => false,
            "is_tp_property_damage" => false,
            "is_tyre_secure" => $is_tyre_secure,
            "is_vintage_car_discount" => false,
            "is_wheel_rim" => false,
            "is_zero_dep" => $is_zero_dept,
            "key_replacement_premium" => round($key_replacement_premium, 2),
            "ll_paid_driver_premium" => round(($ll_paid_driver ?? 0.0), 2),
            "ll_employee_premium" => round(($ll_paid_employee ?? 0.0), 2),
            "lpg_cng_idv" => $lpg_cng_SI,
            "lpg_cng_premium" => round($lpg_cng_premium, 2),
            "lpg_cng_tp_premium" => $lpg_cng_tp_premium,
            "lpg_cng_total_premium" => ($lpg_cng_premium + $lpg_cng_tp_premium),
            "manufacturing_year" => date('Y', strtotime('01-' . ($proposalDetails->vehicle_manf_year ?? $corporateData->manufacture_year))),
            "membershipNo" => null,
            "ncb" => $ncb,
            "ncb_protection_premium" => $ncb_protection_premium,
            "need_reminders" => false,
            "nonelectrical_accessories" => $non_electrical_accessories,
            "nonelectrical_accessories_premium" => $non_electrical_accessories_premium,
            "odpremium" => round($odpremium, 2),
            "od_discount_premium" => round(($other_discount * (-1)), 2),
            "other_extra_premium" => 0.0,
            "pa_owner_driver_premium" => round($pa_owner_driver_premium, 2),
            "pa_unnamed_passenger" => ($pa_unnamed_passenger_premium) > 0 ? $pa_unnamed_passenger : 0,
            "pa_unnammed_passanger_sumassured" => ($pa_unnamed_passenger_premium) > 0 ? $pa_unnamed_passenger_si : 0,
            "pa_unnamed_passenger_premium" => round($pa_unnamed_passenger_premium, 2),
            "personal_belonging_premium" => round($personal_belongings_premium, 2),
            //"policy_insurer" => $policy_insurer,
            "policy_startdate_tp" => $policy_startdate_tp,
            "policy_enddate_tp" => $policy_enddate_tp,
            "policy_startdate" => $policy_start_date,
            "policy_enddate" => $policy_end_date,
            "premium_payable" => round($premium_payable, 2),//(float) ($proposalDetails->final_payable_amount ?? 0),
            "prev_coverage_type" => null,
            "prev_year_payment_id" => null,
            "previous_insurer_id" => empty($previous_insurer_id) ? null : (int) $previous_insurer_id,
            "previous_insurer_tp_id" => empty($previous_insurer_id) ? null : (int) $previous_insurer_id,
            "previous_policy_startdate" => !$has_previous_policy ? null : date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date . ' -1 Year +1 Day')),
            "previous_policy_enddate" => !$has_previous_policy ? null : date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date)),
            "previous_policy_startdate_tp" => $previous_policy_startdate_tp,
            "previous_policy_enddate_tp" => $previous_policy_enddate_tp,
            "previous_policy_number" => !$has_previous_policy ? null : ($proposalDetails->previous_policy_number ?? null),
            "previous_policy_number_tp" => !$has_previous_policy ? null : ($proposalDetails->tp_insurance_number ?? null),
            "previous_yearncb" => (int) $corporateData->previous_ncb,
            "profession" => empty($profession_name) ? ($proposalDetails->occupation ?? '') : $profession_name,
            "purchase_date" => date('Y-m-d', strtotime($proposalDetails->created_date ?? '')),
            "registrationdate" => date('Y-m-d', strtotime($corporateData->vehicle_register_date)),
            "registrationNo" => Str::replace('-', '', $proposalDetails->vehicale_registration_number ?? ''),
            "renewal_bitly_link" => null,
            "renewal_remap" => false,
            "repair_glass_fibre_premium" => 0.0,
            "return_invoice_premium" => round($rti_premium, 2),
            "road_assistance_premium" => round($rsa_premium, 2),
            "rodent_bite_cover_premium" => 0.0,
            "rti_premium" => round($rti_premium, 2),
            "rto" => null,
            "rto_id" => empty($rto_id) ? null : (int) $rto_id,
            "servicetax" => round($servicetax, 2),//(float) ($proposalDetails->service_tax_amount ?? 0),
            "term_year" => $term_year,
            "term_year_tp" => $term_year_tp,
            "towing_premium" => 0.0,
            "tppd_premium" => round(($tppd_premium * (-1)), 2),
            "tppremium" => round($tppremium, 2),
            "tyre_secure_premium" => round($tyre_secure_premium, 2),
            "underloan" => $underloan,
            "urn" => $proposalDetails->ckyc_number ?? null, // CKYC
            "user_id" => null,
            "variant_id" => $rb_variant_id,
            "vehicle_id" => null,
            "vehicle_idv" => $vehicle_idv,//(float) ($proposalDetails->idv ?? 0),
            "verification_raised_at" => empty($proposalDetails->created_date ?? '') ? null : date('Y-m-d H:i:s', strtotime($proposalDetails->created_date)), // CKYC
            "vintage_car_discount_premium" => 0.0,
            "voluntary_deductible_value" => $voluntary_deductible_value,
            "voluntary_deductible_premium" => round(($voluntary_deductible_premium * (-1)), 2),
            "wheel_rim_premium" => 0.0,
            "zero_dep_premium" => round($zero_dep_premium, 2),
            "is_whatsapp" => $corporateData->whatsapp_consent == 'Y',
        ];
    }

    /** Prepare Payment Data
     * return array()
     */
    protected function paymentData(UserProposal $proposalDetails, $proposal_submit = false)
    {
        if (empty($proposalDetails)) {
            return false;
        }
        $journeyStage = JourneyStage::where('user_product_journey_id', $this->enquiry_id)->pluck('stage')->first();
        $paymentData = PaymentRequestResponse::where(['user_product_journey_id' => $this->enquiry_id, 'active' => 1])->first();
        if (!$paymentData && !$proposal_submit && $this->stage != 'inspection' && !in_array(strtolower($journeyStage), array_map( 'strtolower', [ STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_REJECTED']]))) {
            return false;
        }

        $policyDetails = PolicyDetails::where('proposal_id', $proposalDetails->user_proposal_id)->select('policy_number', 'pdf_url')->first();

        $comm_city_id = DB::table('fyntune_city_master')->where('city_name', $proposalDetails->city)->pluck('rb_code')->first();

        $insurer_name = DB::table('fyntune_previous_insurer_master')->whereRaw('? LIKE CONCAT("%", identifier, "%")', $proposalDetails->ic_name ?? '')->pluck('prev_rb_insurer')->first();

        $payment_status = [
            'payment success' => 'success',
            'succcess' => 'success',
            'payment initiated' => 'pending',
            'payment failed' => 'failure',
            'failure' => 'failure',
        ];

        $payment_status = $payment_status[strtolower($paymentData->status ?? '')] ?? 'pending';

        $pdf_url = $policyDetails->pdf_url ?? null;
        $policy_number = $policyDetails->policy_number ?? null;

        //If payment is done and PDF is not generated, pass status as payment_deducted - 19-07-2022
        if (!empty($journeyStage) && in_array(strtolower($journeyStage), array_map( 'strtolower', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['PAYMENT_RECEIVED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])) && ((empty($pdf_url)) || empty($policy_number))) {
            $payment_status = 'payment_deducted';
        }
        // If inspection is done then we need to pass payment_status as 'pending' - Jira Tickets - 1991 and 574.
        if ($this->stage == 'inspection' || in_array(strtolower($journeyStage), array_map( 'strtolower', [ STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_REJECTED'], STAGE_NAMES['INSPECTION_REJECTED'], STAGE_NAMES['INSPECTION_ACCEPTED']]))) {
            $payment_status = strtolower($journeyStage) == strtolower( STAGE_NAMES['INSPECTION_ACCEPTED'] ) ? 'pending' : 'inspection_raised';
        }
        
        if (!empty($pdf_url) && !empty($policy_number)) {
            $payment_status = 'success';
        }
        if (empty($policy_number) && $payment_status == 'success') {
            $payment_status = 'payment_deducted';
        }

        return [
            "broker_qualified_person_id" => null,
            "cheque_date" => null,
            "cheque_micr_code" => null,
            "cheque_number" => null,
            "cheque_submission_branch_id" => null,
            "co_reference_id" => null,
            "cover_note_number" => null,
            "creation_time" => $paymentData->created_at ?? date('Y-m-d H:i:s'),
            "created_by_id" => null,
            "discrepancy_category" => null,
            "discrepancy_sub_category" => null,
            "failure_datetime" => null,
            "insurer_city" => empty($comm_city_id) ? null : (int) $comm_city_id,
            "insurerName" => empty($insurer_name) ? null : $insurer_name,
            "modified_at" => $paymentData->updated_at ?? null,
            "payment_amount" => (float) ($paymentData->amount ?? null),
            "payment_date" => $paymentData->created_at ?? date('Y-m-d H:i:s'),
            "payment_instrument" => 4,
            "payment_instrument_detail" => null,
            "payment_mode" => "Online",
            "payment_status" => $payment_status,
            "policyNo" => $policyDetails->policy_number ?? null,
            "enquiry_details" => [
                "data" => "Something",
            ],
            "referenceId" => $paymentData->order_id ?? null,
            "remarks" => json_encode([
                "status" => true,
                "payment_date" => $paymentData->created_at ?? null,
                "Partner_name" => "RenewBuy",
                "quote_number" => $paymentData->order_id ?? null,
                "amount" => (float) ($paymentData->amount ?? null),
                "transaction_date" => $paymentData->created_at ?? null,
                "Agreement_Code" => "6660",
                "payment_mode" => "netbanking",
                "proposal_id" => $paymentData->user_proposal_id ?? null,
                "error_code" => "(None, None)",
                "transaction_id" => $paymentData->order_id ?? null,
                "customer_name" => implode(' ', [$proposalDetails->first_name, $proposalDetails->last_name]),
            ]),
            "source_product" => 4,
            // If payment is initiated or payment is failed don't send payment success_datetime - RB : 28-07-2022
            //"success_datetime" => in_array(strtolower($paymentData->status ?? ''), [ STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['PAYMENT_FAILED']]) ? null : ($paymentData->updated_at ?? null),
            // Instead of checking payment table's status, we need to add check on $payment_status - 28-10-2022
            "success_datetime" => in_array($payment_status, ['payment_deducted', 'success']) ? ($paymentData->updated_at ?? null) : null,
            "transactionno" => $paymentData->order_id ?? null,
        ];
    }

    /** Prepare Policy Data
     * return array()
     */
    protected function policyData(UserProposal $proposalDetails)
    {
        // proposal data is overwrite in above function
        $proposalDetails = (object) $proposalDetails->getOriginal();

        if (empty($proposalDetails)) {
            return static::dummyPolicyData();
        }
        $policyDetails = PolicyDetails::where('proposal_id', $proposalDetails->user_proposal_id)->first();
        if (!$policyDetails) {
            return static::dummyPolicyData();
        }
        if (empty($policyDetails->policy_number)) {
            return static::dummyPolicyData();
        }
        $corporateData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $this->enquiry_id)->first();
        $has_previous_policy = !($corporateData->business_type == 'newbusiness' || strtolower($corporateData->previous_policy_type) == 'not sure');
        $quoteLog = QuoteLog::where('user_product_journey_id', $this->enquiry_id)->first();
        $company_alias = $quoteLog->premium_json['company_alias'] ?? null;
        $previous_ic_full_name = $previous_insurer_id = null;
        if ($corporateData->business_type != 'newbusiness' && $company_alias && $has_previous_policy) {
            $previous_ic_full_name = PreviousInsurerList::where([
                'code' => $proposalDetails->previous_insurance_company,
                'company_alias' => $company_alias,
            ])->pluck('name')->first();
            $previous_insurer_id = DB::table('fyntune_previous_insurer_master')->whereRaw('? LIKE CONCAT("%", identifier, "%")', $previous_ic_full_name ?? '')->pluck('rb_code')->first();
        }
        $product_sub_type_id = UserProductJourney::where('user_product_journey_id', $this->enquiry_id)->pluck('product_sub_type_id')->first();
        $product = [
            1 => 'car',
            2 => 'bike',
            5 => 'pcv',
            6 => 'pcv',
            7 => 'pcv',
            9 => 'gcv',
            10 => 'pcv',
            11 => 'pcv',
            12 => 'pcv',
            13 => 'gcv',
            14 => 'gcv',
            15 => 'gcv',
            16 => 'gcv',
            17 => 'gcv',
            18 => 'gcv',
        ];
        $imd_code = '';
        if ($product_sub_type_id && $product_sub_type_id > 0) {
            $imd_code = DB::table('rb_ic_intermediary_codes')->where([
                'product_sub_type' => $product[(int) $product_sub_type_id] ?? '',
                'ic_alias' => $company_alias,
            ])->pluck('imd_code')->first();
        }


        //tp start date & end date code
        $policy_startdate_tp = !empty($proposalDetails->tp_start_date) ? date('Y-m-d', strtotime($proposalDetails->tp_start_date)) : (!empty($proposalDetails->policy_start_date) ? date('Y-m-d', strtotime($proposalDetails->policy_start_date)) : null);

        if ($corporateData->business_type == 'newbusiness') {
            //Third-Party Policy State/End Date
            $policy_startdate_tp = !empty($proposalDetails->policy_start_date) ? date('Y-m-d', strtotime($proposalDetails->policy_start_date ?? '')) : null;
        } elseif ($corporateData->policy_type == 'own_damage') {
            $policy_startdate_tp = $policy_enddate_tp = null;
        } elseif ($corporateData->policy_type == 'comprehensive') {
            $policy_startdate_tp = !empty($proposalDetails->policy_start_date) ? date('Y-m-d', strtotime($proposalDetails->policy_start_date ?? '')) : null;
        }

        $master_policy_id = $quoteLog['master_policy_id'];
        $premium_type_code = MasterPolicy::where('policy_id', $master_policy_id)
        ->join('master_premium_type as m', 'm.id', '=', 'master_policy.premium_type_id')
        ->select('m.premium_type_code')
        ->get()
        ->first();

        if (
            empty($policy_startdate_tp) &&
            !empty($proposalDetails->tp_start_date) &&
            !empty($proposalDetails->tp_end_date) &&
            in_array($premium_type_code->premium_type_code ?? '', ['third_party', 'third_party_breakin'])
        ) {
            $policy_startdate_tp =  date('Y-m-d', strtotime($proposalDetails->tp_start_date));
        }

        return [
            "active" => true,
            "claim_previousyear" => $corporateData->is_claim == 'Y' ? true : false,
            "coverage_type" => ucwords(Str::replace('_', ' ', $corporateData->policy_type)),
            "creation_time" => $policyDetails->created_on,
            "endorse_policy_document" => "",
            "intermediary_code" => $imd_code,
            "isvalid" => true,
            "policy_document" => empty($policyDetails->pdf_url) ? null : $policyDetails->pdf_url,
            "policy_insurer" => $quoteLog->ic_alias ?? '',
            "policy_issuedate" => $policyDetails->created_on,
            "policy_startdate" => empty($proposalDetails->policy_start_date) || in_array($premium_type_code->premium_type_code ?? '', ['third_party', 'third_party_breakin']) ? null : date('Y-m-d', strtotime($proposalDetails->policy_start_date)),
            "policy_startdate_tp" => $policy_startdate_tp,#empty($proposalDetails->tp_start_date) ? null : date('Y-m-d', strtotime($proposalDetails->tp_start_date)),
            "policyno" => $policyDetails->policy_number,
            "policyno_tp" => $policyDetails->policy_number,
            "previous_insurer_id" => empty($previous_insurer_id) ? null : (int) $previous_insurer_id,
            "previous_policy_enddate" => !$has_previous_policy ? null : date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date)),
            "previous_policy_enddate_tp" => !$has_previous_policy ? null : date('Y-m-d', strtotime($corporateData->previous_policy_expiry_date)),
            "previous_policyno" => $proposalDetails->previous_policy_number,
            "previous_yearncb" => (int) $corporateData->previous_ncb,
            "user_id" => null,
            "vehicle_idv" => $quoteLog->idv ?? "",
            "vehicle_idvchosen" => $quoteLog->idv ?? "",
        ];
    }

    /** Prepare Inspection Data
     * return array()
     */
    protected function inspectionData(UserProposal $proposalDetails)
    {
        $breakin_data = CvBreakinStatus::where('user_proposal_id', $proposalDetails->user_proposal_id)->select('breakin_number', 'inspection_date', 'breakin_status', 'payment_end_date', 'created_at')->first();
        if (empty($breakin_data)) {
            return false;
        }
        // SUBMITTED = 0, RECOMMENDED = 1, NOTRECOMMENDED = 2, EXPIRED = 3, ISSUED = 4
        $inspection_stages = [
            'inspection pending' => 0,
            'inspection accept' => 1,
            'inspection reject' => 2,
        ];
        $journeyStage = JourneyStage::where('user_product_journey_id', $this->enquiry_id)->pluck('stage')->first();
        $inspection_status = $inspection_stages[strtolower($journeyStage)] ?? 0;

        if(strtolower($breakin_data->breakin_status) == strtolower( STAGE_NAMES['INSPECTION_APPROVED'])) {
        	$inspection_status = 1;
        }

        if (!empty($breakin_data->payment_end_date) && (strtotime($breakin_data->payment_end_date) < strtotime(now()))) {
            $inspection_status = 3; // EXPIRED
        }

        $policy_data = PolicyDetails::where('proposal_id', $proposalDetails->user_proposal_id)->select('policy_number', 'pdf_url')->first();
        if (!empty($policy_data) && (!empty($policy_data->policy_number) || !empty($policy_data->pdf_url))) {
            $inspection_status = 4; //ISSUED
        }
        $t_plus_2_date = date('Y-m-d H:i:s', strtotime('+48 hours' . $breakin_data->created_at));
        $inspection_expiry_date = $breakin_data->payment_end_date ?? $t_plus_2_date;
        return [
            "inspection_status" => $inspection_status,
            "inspection_submitted_at" => null, // we don't get this from insurer
            "inspection_done_at" => empty($breakin_data->inspection_date) ? null : $breakin_data->inspection_date,
            "inspection_reference_id" => $breakin_data->breakin_number ?? '',
            "inspection_expiry_date" => $inspection_expiry_date,
        ];
    }

    /**
     * Get Addon Premium Amount
     * @param String addon_tag  Tag value we pass to frontend for eg. zeroDepreciation
     * @param Array $premium_json  Stored in quote_log table
     * @param Array $addons  Array from the selected addons table
     * @param String $addon_name  "name" store in the Selected Addon table
     * @return Array (Addon Premium, Is addon selected by user)
     */
    public function getAddonValue(String $addon_tag, array $premium_json, array $addons, String $addon_name)
    {
        if (count($addons) == 0) {
            return [0, false];
        }
        if ($this->premiumDetailsAvailable) {
            return $this->fetchAddonPremiumDetails($addon_tag, $premium_json, $addons, $addon_name);
        }

        if (
            isset($premium_json['addOnsData']['inBuilt'][$addon_tag])
            && array_search($addon_name, array_column($addons, 'name')) !== false
        ) {
            return [$premium_json['addOnsData']['inBuilt'][$addon_tag], true];
        } elseif (
            isset($premium_json['addOnsData']['additional'][$addon_tag])
            && array_search($addon_name, array_column($addons, 'name')) !== false
        ) {
            return [$premium_json['addOnsData']['additional'][$addon_tag], true];
        }
        return [0, false];
    }

    /**
     * Get Addon Premium Amount stored in the Premium Details table
     * @param String addon_tag  Tag value we pass to frontend for eg. zeroDepreciation
     * @param Array $premium_json  Stored in quote_log table
     * @param Array $addons  Array from the selected addons table
     * @param String $addon_name  "name" store in the Selected Addon table
     * @return Array (Addon Premium, Is addon selected by user)
     */

    public function fetchAddonPremiumDetails(String $addon_tag, array $premium_json, array $addons, String $addon_name) {
        $addonTagName = config('kafka.premiumDetails.' . $addon_tag);
        
        if (!empty($addonTagName)) {
            if ($this->premiumDetails[$addonTagName] > 0) {
                return [$this->premiumDetails[$addonTagName], true];
            } elseif (isset($this->premiumDetails['in_built_addons'][$addonTagName])) {
                return [$this->premiumDetails['in_built_addons'][$addonTagName], true];
            }
        }
        return [0, false];
    }

    /**
     * Get CPA Premium
     * @param Array $premium_json  Stored in quote_log table
     * @param Array $cpa_json  Array from the selected addons table
     * @return Integer CPA Premium
     */
    public function getCpaValue(array $premium_json, array $cpa_json)
    {
        if (isset($cpa_json[0]['name']) && $cpa_json[0]['name'] == 'Compulsory Personal Accident') {
            if ($this->premiumDetailsAvailable) {
                return $this->premiumDetails['compulsory_pa_own_driver'];
            } else if (isset($cpa_json[0]['tenure']) && $cpa_json[0]['tenure'] > 0) {
                if ($cpa_json[0]['tenure'] > 1 && isset($premium_json['multiYearCpa'])) {
                    return $premium_json['multiYearCpa'];
                } else {
                    return $premium_json['compulsoryPaOwnDriver'];
                }
            } else {
                return $premium_json['compulsoryPaOwnDriver'];
            }
        }
        return 0;
    }

    /**
     * Get Accessories SumInsured and Premium
     * @param String accessories_tag  Tag value we pass to frontend for eg. motorElectricAccessoriesValue
     * @param Array $premium_json  Stored in quote_log table
     * @param Array $accessories  Array from the selected addons table
     * @param String $accessory_name  "name" store in the Selected Addon table
     * @return Array (SumInsured, Accessory Premium)
     */
    public function getAccessoriesValue(String $accessories_tag, array $premium_json, array $accessories, String $accessory_name)
    {
        if (count($accessories) == 0) {
            return [0, 0];
        }

        if ($this->premiumDetailsAvailable) {
            return $this->getAccessoriesPremiumValue($accessories_tag, $premium_json, $accessories, $accessory_name);
        }

        if (
            isset($premium_json[$accessories_tag])
            && ($premium_json[$accessories_tag] > 0)
            && ($index = array_search($accessory_name, array_column($accessories, 'name'))) !== false
        ) {
            return [$accessories[$index]["sumInsured"], (float) $premium_json[$accessories_tag]];
        }else if (
            isset($premium_json[$accessories_tag])
            && ($index = array_search($accessory_name, array_column($accessories, 'name'))) !== false
        )
        {
            if(isset($premium_json['company_alias']) && ($premium_json['company_alias'] == 'godigit' || $premium_json['company_alias'] == 'shriram'))
            {
                return [$accessories[$index]["sumInsured"], (float) $premium_json[$accessories_tag]];
            }

            if ((($premium_json['company_alias']) ?? '' == 'oriental' && in_array($accessories_tag, ['motorNonElectricAccessoriesValue']))) {
                return [$accessories[$index]["sumInsured"], (float) $premium_json[$accessories_tag]];
            }
        }
        return [0, 0];
    }

    /**
     * Get Accessories SumInsured and Premium
     * Premium is fetched from the premium_details table
     * @param String accessories_tag  Tag value we pass to frontend for eg. motorElectricAccessoriesValue
     * @param Array $premium_json Stored in quote_log table
     * @param Array $accessories Array from the selected addons table
     * @param String $accessory_name "name" store in the Selected Addon table
     * @return Array (SumInsured, Accessory Premium)
     */
    public function getAccessoriesPremiumValue(String $accessories_tag, array $premium_json, array $accessories, String $accessory_name) {
        $accessoriesTagName = config('kafka.premiumDetails.' . $accessories_tag);
        if (
            !empty($accessoriesTagName)
            && ($this->premiumDetails[$accessoriesTagName] > 0)
            && ($index = array_search($accessory_name, array_column($accessories, 'name'))) !== false
        ) {
            return [$accessories[$index]["sumInsured"], (float) $this->premiumDetails[$accessoriesTagName]];
        }else if (
            isset($this->premiumDetails[$accessoriesTagName])
            && ($index = array_search($accessory_name, array_column($accessories, 'name'))) !== false
        )
        {
            if(isset($premium_json['company_alias']) && ($premium_json['company_alias'] == 'godigit' || $premium_json['company_alias'] == 'shriram'))
            {
                return [$accessories[$index]["sumInsured"], (float) $this->premiumDetails[$accessoriesTagName]];
            }

            if ((($premium_json['company_alias']) ?? '' == 'oriental' && in_array($accessories_tag, ['motorNonElectricAccessoriesValue']))) {
                return [$accessories[$index]["sumInsured"], (float) $this->premiumDetails[$accessoriesTagName]];
            }
        }
        return [0, 0];
    }

    /**
     * Get Discount SumInsured and Premium
     * @param String discount_tag  Tag value we pass to frontend for eg. voluntaryExcess
     * @param Array $premium_json  Stored in quote_log table
     * @param Array $discounts  Array from the selected addons table
     * @param String $discount_name  "name" store in the Selected Addon table
     * @return Array (SumInsured, Discount Premium)
     */
    public function getDiscountValue(String $discount_tag, array $premium_json, array $discounts, String $discount_name)
    {
        if (count($discounts) == 0) {
            return [0, 0];
        }

        if ($this->premiumDetailsAvailable) {
            return $this->getPremiumDiscountValue($discount_tag, $premium_json, $discounts, $discount_name);
        }
        if (
            isset($premium_json[$discount_tag])
            && ($index = array_search($discount_name, array_column($discounts, 'name'))) !== false
        ) {
            return [$discounts[$index]["sumInsured"], $premium_json[$discount_tag]];
        }
        return [0, 0];
    }

    /**
     * Get Discount SumInsured and Premium
     * Premium is fetched from the premium_details table
     * @param String discount_tag  Tag value we pass to frontend for eg. voluntaryExcess
     * @param Array $premium_json  Stored in quote_log table
     * @param Array $discounts  Array from the selected addons table
     * @param String $discount_name  "name" store in the Selected Addon table
     * @return Array (SumInsured, Discount Premium)
     */
    public function getPremiumDiscountValue(String $discount_tag, array $premium_json, array $discounts, String $discount_name)
    {
        $discountTagName = config('kafka.premiumDetails.'. $discount_tag);
        if (
            !empty($discountTagName)
            && ($index = array_search($discount_name, array_column($discounts, 'name'))) !== false
        ) {
            return [$discounts[$index]["sumInsured"], $this->premiumDetails[$discountTagName]];
        }
        return [0, 0];
    }

    

    public function getAdditionalCoversValue(String $add_cover_tag, array $premium_json, array $additional_covers, String $cover_name)
    {
        if (count($additional_covers) == 0) {
            return [0, 0];
        }

        if ($this->premiumDetailsAvailable) {
            return $this->getPremiumAdditionalCoversValue($add_cover_tag, $premium_json, $additional_covers, $cover_name);
        }

        if (
            isset($premium_json[$add_cover_tag])
            && ($index = array_search($cover_name, array_column($additional_covers, 'name'))) !== false
        ) {
            if ($cover_name == 'LL paid driver/conductor/cleaner') {
                if ($add_cover_tag == 'llPaidCleanerPremium') {
                    return [$additional_covers[$index]["LLNumberCleaner"], $premium_json[$add_cover_tag]];
                } else if ($add_cover_tag == 'llPaidConductorPremium') {
                    return [$additional_covers[$index]["LLNumberConductor"], $premium_json[$add_cover_tag]];
                } else if ($add_cover_tag == 'llPaidDriverPremium') {
                    return [$additional_covers[$index]["LLNumberDriver"], $premium_json[$add_cover_tag]];
                }
            }else if($cover_name == 'Geographical Extension') {
                return [1, (float) $premium_json[$add_cover_tag]];
            }
            return [$additional_covers[$index]["sumInsured"], $premium_json[$add_cover_tag]];
        }
        return [0, 0];
    }

    public function getPremiumAdditionalCoversValue(String $add_cover_tag, array $premium_json, array $additional_covers, String $cover_name)
    {
        $coverTagName = config('kafka.premiumDetails.' . $add_cover_tag);
        if (
            !empty($coverTagName)
            && ($index = array_search($cover_name, array_column($additional_covers, 'name'))) !== false
        ) {
            if ($cover_name == 'LL paid driver/conductor/cleaner') {
                if ($add_cover_tag == 'llPaidCleanerPremium') {
                    return [$additional_covers[$index]["LLNumberCleaner"], $this->premiumDetails[$coverTagName]];
                } else if ($add_cover_tag == 'llPaidConductorPremium') {
                    return [$additional_covers[$index]["LLNumberConductor"], $this->premiumDetails[$coverTagName]];
                } else if ($add_cover_tag == 'llPaidDriverPremium') {
                    return [$additional_covers[$index]["LLNumberDriver"], $this->premiumDetails[$coverTagName]];
                }
            }else if($cover_name == 'Geographical Extension') {
                return [1, (float) $this->premiumDetails[$coverTagName]];
            }
            return [$additional_covers[$index]["sumInsured"], $this->premiumDetails[$coverTagName]];
        }
        return [0, 0];
    }

    protected function createLog($log)
    {
        DB::table('kafka_data_push_logs')->insert([
            'user_product_journey_id' => $this->enquiry_id,
            'stage' => $this->stage,
            'request' => json_encode($log),
            'created_on' => now(),
            'source' => $this->source,
        ]);
    }

    /**
     * Push the Data to Kafka Topic
     */
    protected function pushData(object $message)
    {
        if (config('kafka.security.protocol.type') == 'plaintext') {
            $configOptions = [
                'security.protocol' => 'plaintext',
            ];
        } else {
            $configOptions = [
                'security.protocol' => 'ssl',
                //"ssl.ca.location" => "/home/devops/kafka.client.truststore.jks",
                "ssl.keystore.location" => env("KAFKA_KEYSTORE_LOCATION"), //"/home/devops/kafka.client.keystore.jks",
                "ssl.keystore.password" => env("KAFKA_KEYSTORE_PASSWORD"),
                "ssl.key.password" => env("KAFKA_KEY_PASSWORD"),
            ];
        }
        $producer = Kafka::publishOn(env('KAFKA_TOPIC'))
            ->withConfigOptions($configOptions)
            ->withMessage($message)
            ->withDebugEnabled();

        $producer->send();
    }

    /**
     * If there are no records found then send a dummy object to RB team,
     * instead of empty object (later array get's converted to object)
     * @return Array Sample array sent by RB team
     */
    static function dummyPolicyData() {
        return [
            "dummy_policy_data" => true,
            "active" => true,
            "claim_previousyear" => false,
            "coverage_type" => null,
            "creation_time" => null,
            "endorse_policy_document" => null,
            "intermediary_code" => null,
            "isvalid" => true,
            "policy_document" => null,
            "policy_insurer" => null,
            "policy_issuedate" => null,
            "policy_startdate" => null,
            "policy_startdate_tp" => null,
            "policyno" => null,
            "policyno_tp" => null,
            "previous_insurer_id" => null,
            "previous_policy_enddate" => null,
            "previous_policy_enddate_tp" => null,
            "previous_policyno" => null,
            "previous_yearncb" => null,
            "user_id" => null,
            "vehicle_idv" => null,
            "vehicle_idvchosen" => null
        ];
    }

    public function vahanData($enquiryId)
    {
        if (config('constants.motorConstant.IS_VAHAN_DATA_PUSH_ENABLED') != 'Y') {
            return [];
        }
        $quoteVahanLogs = [];
        $proposalVahanLogs = [];
        try {
            $results = VahanServiceLogs::where('enquiry_id', $enquiryId)->get()->toArray();
            foreach ($results as $r) {
                $response = json_decode($r['response'], true);
                if (!empty($response['servicelist'] ?? [])) {
                    foreach ($response['servicelist'] as $vendor => $service) {
                        if (isset($service['original'])) {
                            $service =  $service['original'];
                        }
                        if (isset($service['data']['data']['dataSource'])) {
                            $service = $service['data'];
                        }
                        $source = null;
                        if (!empty($service['data']['dataSource'] ?? null)) {
                            if ($service['data']['dataSource'] == 'online') {
                                $source = 'Api';
                            } else {
                                $source = 'Database';
                            }
                        }
                        $logs = [
                            'vendor' => $vendor,
                            'status' => ($service['data']['status'] ?? 101) == 100 ? 'yes' : 'no',
                            'results' => ($service['data']['status'] ?? 101) == 100 ? "data found" : "no data",
                            'source' => $source
                        ];
            
                        if ($r['stage'] == 'quote') {
                            $quoteVahanLogs[] = $logs;
                        } else {
                            $proposalVahanLogs[] = $logs;
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            info($th);
        }
        return [
            'quote_stage_vahan_info' => $quoteVahanLogs,
            'proposal_stage_vahan_info' => $proposalVahanLogs,
        ];
    }
}