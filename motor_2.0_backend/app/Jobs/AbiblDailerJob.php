<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use App\Models\AbiblDailerApiLog;
use App\Models\UserProductJourney;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class AbiblDailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(config('abibl_dialer_api') != "Y"){
            return ;
        }
        $from = 0;
        $to = 45;
        $abibl_dailer_attempts = \App\Models\AbiblDailerAttempt::where('attempts', '>', 3)->get('user_product_journey_id')->pluck('user_product_journey_id')->toArray();
        $user_product_journeys = UserProductJourney::whereNotIn('user_product_journey_id', $abibl_dailer_attempts)->where('lead_source', 'ABIBL_MG_DATA')->with(['user_proposal' => function ($query) {
            $query->select(['user_product_journey_id', 'policy_end_date']);
        }])->whereHas('user_proposal', function ($query) use ($from, $to) {
            $query->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') < CURDATE() + INTERVAL {$to} DAY AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') > CURDATE() + INTERVAL {$from} DAY");
        })->whereHas('journey_stage', function ($query) {
            $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
        })->limit(3)->get(['user_product_journey_id', 'created_on']);
        if (empty($user_product_journeys)) {
            return;
        }

        foreach ($user_product_journeys as $key => $user_product_journey) {
            $abibl_dailer_attempts = \App\Models\AbiblDailerAttempt::where('user_product_journey_id', $user_product_journey->user_product_journey_id)->first();
            if ($abibl_dailer_attempts) {
                if ($abibl_dailer_attempts->attempts < 2) {
                    if ($abibl_dailer_attempts->next_attempts_on == now()->format('Y-m-d')) {
                        $abibl_dailer_attempts->attempts = $abibl_dailer_attempts->attempts + 1;
                        $abibl_dailer_attempts->save();
                    }
                } else {
                    continue;
                }
            } else {
                \App\Models\AbiblDailerAttempt::create([
                    'user_product_journey_id' => $user_product_journey->user_product_journey_id,
                    'attempts' => 1,
                    'next_attempts_on' => Carbon::parse($user_product_journey->user_proposal->policy_end_date)->subDays(10),
                ]);
            }
            // $proposal_report = httpRequestNormal(url('api/proposalReports'), 'POST', ['enquiry_id' => $user_product_journey->journey_id], [], [], [], false)['response']['data'][0];
            // $productSubType = \App\Models\MasterProductSubType::with('parent')->where('product_sub_type_code', $proposal_report['product_type'])->first()->parent->product_sub_type_id;
            // $user_product_journey_id = UserProductJourney::create([
            //     'product_sub_type_id' => $productSubType,
            //     // 'user_fname' => null,
            //     // 'user_lname' => ,
            //     'user_email' => $proposal_report['proposer_emailid'],
            //     'user_mobile' => $proposal_report['proposer_mobile'],
            //     // 'sub_source' => $request->sub_source,
            //     // 'campaign_id' => $request->campaign_id,
            // ]);
            // httpRequestNormal(url('api/saveQuoteRequestData'), "POST", [
            //     "stage" => "1",
            //     "userProductJourneyId" => $user_product_journey_id->journey_id,
            //     "enquiryId" => $user_product_journey_id->journey_id,
            //     "whatsappConsent" => true,
            // ], [], [], [], false);

            // $section = $proposal_report['product_type'];
            // $productSubType = $productSubType;
            // $vehicleDetails = httpRequestNormal(url('api/getVehicleDetails'), 'get', [
            //     'enquiryId' => $user_product_journey_id->journey_id,
            //     'registration_no' => $proposal_report['vehicle_registration_number'],
            //     "productSubType" => $productSubType,
            //     "section" => $section,
            //     "is_renewal" => "Y",
            // ], [], [
            //     'Accept' => 'application/json'
            // ], [], false)['response'];
            // if (empty($vehicleDetails['data']['redirection_data']['redirection_url'])) {
            //     $urls = httpRequestNormal(url('api/frontendUrl'), 'GET', [], [], [], [], false)['response']['data'];
            //     if ($vehicleDetails['data']['additional_details']['product_sub_type_id'] == 'car') {
            //         $url = $urls['car_frontend_url'] . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
            //     } else if ($vehicleDetails['data']['additional_details']['product_sub_type_id'] == 'bike') {
            //         $url = $urls['bike_frontend_url'] . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
            //     } else {
            //         $url = $urls['bike_frontend_url'] . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
            //     }
            // }else {
            //     $url = $vehicleDetails['data']['redirection_data']['redirection_url'];
            // }
            // dd($url);
            //     httpRequestNormal(url('api/saveQuoteRequestData'), "POST", [
            //         "isRenewalRedirection" => "N",
            //         "enquiryId" => $user_product_journey_id->journey_id,
            //         "vehicleRegistrationNo" => $proposal_report['vehicle_registration_number'],
            //         "userProductJourneyId" => $user_product_journey_id->journey_id,
            //         "corpId" => "",
            //         "userId" => null,
            //         "productSubTypeId" =>  $productSubType,
            //         "fullName" => $vehicleDetails['data']['additional_details']['fullName'],
            //         "firstName" => $vehicleDetails['data']['additional_details']['firstName'],
            //         "lastName" => $vehicleDetails['data']['additional_details']['lastName'],
            //         "emailId" => $proposal_report['proposer_emailid'],
            //         "mobileNo" => $proposal_report['proposer_mobile'],
            //         "policyType" => $vehicleDetails['data']['additional_details']['policyType'],
            //         "businessType" => $vehicleDetails['data']['additional_details']['businessType'],
            //         "rto" => $vehicleDetails['data']['additional_details']['rto'],
            //         "manufactureYear" => $vehicleDetails['data']['additional_details']['manufactureYear'],
            //         "version" => $vehicleDetails['data']['additional_details']['version'],
            //         "versionName" => $vehicleDetails['data']['additional_details']['versionName'],
            //         "vehicleRegisterAt" => $vehicleDetails['data']['additional_details']['vehicleRegisterAt'],
            //         "vehicleRegisterDate" => $vehicleDetails['data']['additional_details']['vehicleRegisterDate'],
            //         "vehicleOwnerType" => $vehicleDetails['data']['additional_details']['vehicleOwnerType'],
            //         "hasExpired" => $vehicleDetails['data']['additional_details']['hasExpired'],
            //         "isNcb" => $vehicleDetails['data']['additional_details']['isNcb'],
            //         "isClaim" => $vehicleDetails['data']['additional_details']['isClaim'],
            //         "fuelType" => $vehicleDetails['data']['additional_details']['fuelType'],
            //         "vehicleUsage" => $vehicleDetails['data']['additional_details']["vehicleUsage"],
            //         "vehicleLpgCngKitValue" => "",
            //         "previousInsurer" => "",
            //         "previousInsurerCode" => "",
            //         "previousPolicyType" => $vehicleDetails['data']['additional_details']["previousPolicyType"],
            //         "modelName" => $vehicleDetails['data']['additional_details']['modelName'],
            //         "manfactureName" => $vehicleDetails['data']['additional_details']['manfactureName'],
            //         "ownershipChanged" => $vehicleDetails['data']['additional_details']['ownershipChanged'],
            //         "engineNo" => $vehicleDetails['data']['results'][0]['vehicle']['eng_no'],
            //         "chassisNo" => $vehicleDetails['data']['results'][0]['vehicle']['chasi_no'],
            //         "vehicleColor" => $vehicleDetails['data']['results'][0]['vehicle']['color'],
            //         "leadJourneyEnd" => true,
            //         "stage" => 11,
            //         // "lsq_stage" => "Quote Seen",
            //         "applicableNcb" => $vehicleDetails['data']['additional_details']['applicableNcb'],
            //         "manfactureId" => $vehicleDetails['data']['additional_details']['manfactureId'],
            //         "model" => $vehicleDetails['data']['additional_details']['model'],
            //         "policyExpiryDate" => $vehicleDetails['data']['additional_details']['policyExpiryDate'],
            //         "previousNcb" => $vehicleDetails['data']['additional_details']['previousNcb'],
            //         "previous_insurer" => $vehicleDetails['data']['additional_details']["previous_insurer"],
            //         "previous_insurer_code" => $vehicleDetails['data']['additional_details']["previous_insurer_code"],
            //         'journeyType' => ""
            //     ]);

            //     httpRequestNormal(url('api/updateUserJourney'), 'POST', ["enquiryId" => $user_product_journey_id->journey_id, "leadStageId" => 2], [], [], [], false);
            // }

            $proposal_report = httpRequestNormal(url('api/proposalReports'), 'POST', ['enquiry_id' => $user_product_journey->journey_id], [], [], [], false)['response'];
            if (!isset($proposal_report['data'][0])) {
                continue;
            }
            $proposal_report = $proposal_report['data'][0];
            $proposal_report = collect($proposal_report)->only("first_name", "last_name", "trace_id", "proposer_name", "proposer_dob", "vehicle_registration_number", "proposer_email_id", "product_name", "vehicle_make", "vehicle_model", "vehicle_version", "vehicle_cubic_capacity", "vehicle_fuel_type", "policy_type", "vehicle_registration_date", "previous_policy_expiry_date", "previous_ncb", "ncb_percentage", "vehicle_manufacture_year", "ncb_claim", "gender_name", "policy_start_date", "policy_end_date", "pincode", "engine_number", "chassis_number", "policy_term", "previous_insurer", "previous_policy_number", "cpa_policy_start_date", "cpa_policy_end_date", "nominee_dob", "nominee_relationship", "nominee_age", "nominee_name", "tp_start_date", "tp_end_date", "tp_policy_number", "tp_prev_company", "breakin_number", "breakin_status", "sum_assured", "policy_period", "prev_policy_type", "zero_dep", "od_discount", "section", "product_type", "sub_product_type", "selected_add_ons", "source", "sub_source", "campaign_id", "proposer_mobile", "quote_url")->toArray();
            $proposer_mobile = $proposal_report['proposer_mobile'];
            unset($proposal_report['proposer_mobile']);

            $proposal_report["source"] = "ABIBL";
            $proposal_report["sub_source"] = "IVR Bot";
            $proposal_report["campaign_id"] = config('ABIBL_CAMPAIGN_ID');
            $proposal_report["od_discount"] = "80";

            $proposal_report["vehicle_registration_date"] = Carbon::parse($proposal_report["vehicle_registration_date"])->format('Y-m-d');
            $proposal_report["previous_policy_expiry_date"] = Carbon::parse($proposal_report["previous_policy_expiry_date"])->format('Y-m-d');
            // $proposal_report["vehicle_manufacture_year"] = Carbon::parse($proposal_report["vehicle_manufacture_year"])->format('Y-m');
            $proposal_report["proposer_dob"] = Carbon::parse($proposal_report["proposer_dob"])->format('Y-m-d');
            $payload_id = time() . rand();
            $data = [
                "payloadId" => $payload_id,
                "encrypted" => false,
                "encryptedPayload" => "",
                "payload" => [
                    "expiry" => now()->addDays(5)->endOfDay()->format('Y-m-d H:i:s'),
                    "msisdn" => collect(["7798274343", "9820236591", "9892650778", "9930967626", "9029148033", "7021671291", "9111722777"])->random()/* $proposer_mobile */,
                    "language" => "en",
                    "requestId" => $payload_id,
                    "serviceId" => "abibl_vehicle_renewal_base_upload",
                    "external_user_id" => config('ABIBL_EXTERNAL_USER_ID'),
                    "data" => $proposal_report,
                ],
            ];
            $response = httpRequest('dailer', $data);

            AbiblDailerApiLog::create([
                'user_product_journey_id' => $user_product_journey->user_product_journey_id,
                'request' => $response['request'],
                'response' => $response['response'],
            ]);
        }
    }
}
