<?php

namespace App\Jobs;

use App\Http\Controllers\Extra\PosToPartnerUtility;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Illuminate\Bus\Queueable;
use App\Models\CvAgentMapping;
use App\Models\JourneyStage;
use App\Models\MasterCompany;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\MasterProductSubType;
use App\Models\MasterRto;
use App\Models\ProposalExtraFields;
use App\Models\QuoteLog;
use App\Models\RenewalDataMigrationStatus;
use App\Models\RenewalUpdationLog;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\RenewalMigrationAttemptLog;

class RenewalDataMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $file_path;
    public $timeout = 0;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($file_path)
    {
        $this->file_path = $file_path;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // $files = Storage::allFiles('renewalDataUpload');

        // foreach ($files as $key => $value) {
            self::renewalDataMigration($this->file_path);
        // }
    }

    static public function renewalDataMigration($file)
    {
        $data = json_decode(Storage::get($file), true);

        if (isset($data['policies']) && !empty($data['policies'])) {
            foreach ($data['policies'] as $value) {

                try {
                    $migrationId = RenewalDataMigrationStatus::create([
                        'policy_number' => $value['previous_policy_number'] ?? NULL,
                        'registration_number' => $value['vehicle_registration_number'] ?? NULL,
                        'request' => json_encode($value),
                        'attempts' => 1,
                        'status' => 'Pending',
                        'action' => ($value['action'] ?? '') == 'update' ? 'update' : 'migration'
                    ])->id;

                    if (($value['action'] ?? '') == 'update') {

                        $updationStatus = "Failed";
                        $journeyId = null;

                        $policyData = PolicyDetails::where('policy_number', $value['previous_policy_number'])
                        ->join('user_proposal as u', 'policy_details.proposal_id', '=', 'u.user_proposal_id')
                        ->whereNotNull('policy_number')
                            ->select('u.user_product_journey_id')
                            ->first();

                        if (!empty($policyData) && !empty($policyData->user_product_journey_id)) {
                            $journeyId = $policyData->user_product_journey_id;
                            $oldAgentDetails = CvAgentMapping::where('user_product_journey_id', $policyData->user_product_journey_id)
                            ->first();

                            $sellerType = '';
                            switch ($value['seller_type']) {
                                case 'E':
                                    $sellerType = 'employee';
                                    break;
                                case 'P':
                                    $sellerType = 'pos';
                                    break;
                                case 'Partner':
                                    $sellerType = 'partner';
                                    break;
                            }

                            if (!empty($value['seller_code'])) {
                                $userRequest =  [
                                    $sellerType => ['seller_code' => [$value['seller_code']]]
                                ];

                                $response = httpRequestNormal(
                                    config('constants.brokerConstant.FETCH_USER_API'),
                                    'POST',
                                    $userRequest,
                                    [],
                                    [],
                                    [],
                                    true
                                )['response'];

                                $response = $response[$sellerType][$value['seller_code']] ?? null;
                                if (($response['status'] ?? null) == true) {
                                    $agentResponse = $response['data'];

                                    $agentData = [
                                        'seller_type'   => $agentResponse['seller_type'] ?? NULL,
                                        'agent_id'      => $agentResponse['seller_id'] ?? NULL,
                                        'user_name'     => $value['seller_code'] ?? NULL,
                                        'agent_name'    => $agentResponse['seller_name'] ?? NULL,
                                        'agent_mobile'  => $agentResponse['mobile'] ?? NULL,
                                        'agent_email'   => $agentResponse['email'] ?? NULL,
                                        // 'unique_number' => $value['seller_type'] ?? NULL,
                                        'aadhar_no'     => $agentResponse['seller_aadhar_no'] ?? NULL,
                                        'pan_no'        => $agentResponse['seller_pan_no'] ?? NULL,
                                        'user_id'       => $agentResponse['seller_id'] ?? NULL,
                                        'pos_key_account_manager' => (isset($agentResponse['seller_type']) && $agentResponse['seller_type'] == "P") ? ($agentResponse["rm_id"] ?? NULL) : ($agentResponse['user_id'] ?? NULL),
                                        'source'       => "agent-update-api",
                                    ];

                                    self::prepareAgentUpdationLogs($agentData, $oldAgentDetails, $value, $migrationId, $policyData->user_product_journey_id);

                                    $updationStatus = "Success";
                                }
                            } else {
                                $agentData = [
                                    'seller_type'   => $value['seller_type'] ?? NULL,
                                    'agent_id'      => $value['seller_id'] ?? NULL,
                                    'user_name'     => $value['seller_username'] ?? NULL,
                                    'agent_name'    => $value['seller_name'] ?? NULL,
                                    'agent_mobile'  => $value['seller_mobile'] ?? NULL,
                                    'agent_email'   => $value['seller_email'] ?? NULL,
                                    // 'unique_number' => $value['seller_type'] ?? NULL,
                                    'aadhar_no'     => $value['seller_aadhar_no'] ?? NULL,
                                    'pan_no'        => $value['seller_pan_no'] ?? NULL,
                                    'user_id' =>       NULL,
                                    'pos_key_account_manager' => (($value['seller_type'] ?? null) == "P") ? ($value["rm_id"] ?? NULL) : ($value['user_id'] ?? NULL),
                                    'source'       => "agent-update",
                                ];

                                self::prepareAgentUpdationLogs($agentData, $oldAgentDetails, $value, $migrationId, $policyData->user_product_journey_id);
                                $updationStatus = "Success";
                            }

                        }
                        RenewalDataMigrationStatus::find($migrationId)->update([
                            'status' => $updationStatus,
                            'user_product_journey_id' => $journeyId
                        ]);

                        if (config('constants.brokerConstant.ENABLE_MANUAL_DASHBOARD_PUSH_IN_MIGRATION') == 'Y') {
                            $url = route('manual-dashboard-push', ['enquiryId' => customEncrypt($journeyId)]);
                            Http::get($url);
                        }

                        continue;
                    }
                    

                    $value = array_map('trim', $value);

                    /* Change All Key to Lowercase */
                    $value = array_change_key_case($value, CASE_LOWER);

                    /* Change "NULL" String to NULL */
                    $value = array_map(function ($v) {
                        return ($v == "NULL") ? NULL : $v;
                    }, $value);


                    $product_sub_type_id = MasterProductSubType::select("product_sub_type_id")->where("product_sub_type_code", Str::upper($value['product_name']))->first();

                    if (empty($value['previous_policy_number']) && !empty($value['secondary_unique_key'])) {
                        $value['partial_journey'] = true;
                    }
                    
                    $policy_details = null;
                    if (!($value['partial_journey'] ?? false)) {
                        $policy_details = PolicyDetails::where('policy_number', $value['previous_policy_number'])
                        ->whereNotNull('policy_number')
                        ->first();
                    }
                    
                    $transaction_date = !empty($value["previous_policy_end_date"]) ? Carbon::parse($value["previous_policy_end_date"])->subYear()->format('Y-m-d H:i:s') : now()->toDateTimeString();
                    
                    if (!empty($value['previous_policy_start_date'])) {
                        $transaction_date = date('Y-m-d H:i:s', strtotime($value["previous_policy_start_date"]));
                    }

                    if (!empty($value['policy_issue_date'])) {
                        $transaction_date = date('Y-m-d H:i:s', strtotime($value["policy_issue_date"]));
                    }

                    // if (config('constants.motorConstant.SMS_FOLDER') == 'bajaj') {
                    //     $transaction_date = null;
                    //     if (empty($policy_details)) {
                    //         $transaction_date = date('Y-m-d H:i:s');
                    //     }
                    // }
                    $user_product_journey = "";
                    # User Product Journey Store.
                    if ($policy_details) {
                        $user_product_journey = UserProposal::with(['journey_stage'])->find($policy_details->proposal_id)->user_product_journey;
                        //logic implemented to block the policy updation for online policies
                        if (config('constants.motorConstant.DISABLE_POLICY_UPDATION_IN_MIGRATION') == 'Y' && $user_product_journey->lead_source != "RENEWAL_DATA_UPLOAD" ) {
                            RenewalDataMigrationStatus::find($migrationId)->update([
                                'status' => 'Failed',
                                'user_product_journey_id' => $user_product_journey->user_product_journey_id ?? null

                            ]);
                            RenewalMigrationAttemptLog::Create([
                                'renewal_data_migration_status_id' => $migrationId,
                                'status' => 'Failed',
                                'extras' => json_encode(['reason' => 'Policy Already Exist', 'IsPolicyExist' => true, 'policy_number' => $value['previous_policy_number'] ?? NULL])
                            ]);
                            continue;
                        }
                        $old_journey_stage = $user_product_journey->journey_stage;
                        if (($value['action'] ?? '') == 'migrate' && ($old_journey_stage->stage ?? '') != STAGE_NAMES['POLICY_ISSUED']) {
                            
                            $updation_data = [
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ];

                            self::prepareJourneyStageUpdationLogs($updation_data, $old_journey_stage, $value, $migrationId, $user_product_journey->user_product_journey_id);
                        }
                    } else {

                        $user_product_journey = null;

                        if (
                            !empty($value['secondary_unique_key'])
                        ) {
                            $proposalExtraFields = ProposalExtraFields::where('upload_secondary_key', $value['secondary_unique_key'])
                            ->first();
    
                            if (!empty($proposalExtraFields)) {
                                $user_product_journey = $proposalExtraFields->user_product_journey;
                            }
                        }

                        if (empty($user_product_journey)) {
                            $user_product_journey = UserProductJourney::create([
                                'user_fname'            => $value['full_name'] ?? null,
                                'user_lname'            => null,
                                'user_mname'            => null,
                                'user_email'            => strtolower(($value['email_address'] ?? null)),
                                'user_mobile'           => $value['mobile_no'] ?? null,
                                'product_sub_type_id'   => $product_sub_type_id["product_sub_type_id"] ?? null,
                                'created_on'            => $transaction_date,
                                'lead_source'           => 'RENEWAL_DATA_UPLOAD'
                            ]);
                        }

                        if ($value['partial_journey'] ?? false) {
                            ProposalExtraFields::create([
                                'enquiry_id' => $user_product_journey->user_product_journey_id,
                                'upload_secondary_key' => $value['secondary_unique_key']
                            ]);
                        }
                    }

                    $rto_code = $new_rc_number = null;
                    if (!empty($value['vehicle_registration_number'])) {
                        if (strtoupper($value['vehicle_registration_number']) != "NEW") {
                            $rc_number = str_split($value['vehicle_registration_number']);
                            if ($rc_number[0] . $rc_number[1] == 'DL') {
                                $vehicle_reg_hyphen = getRegisterNumberWithHyphen($value['vehicle_registration_number']);
                                $reg_array = explode("-", $vehicle_reg_hyphen);
                                $reg_array[1] = (strlen($reg_array[1]) == 1) ? ($reg_array[1] = "0" . $reg_array[1]) : $reg_array[1];
                                $rto_code = $reg_array[0] . "-" . $reg_array[1];
                                $new_rc_number = $reg_array[0] . "-" . $reg_array[1] . '-' . $reg_array[2] . '-' . ($reg_array[3] ?? '');
                            } else {
                                $vehicle_reg_hyphen = getRegisterNumberWithHyphen($value['vehicle_registration_number']);
                                $reg_array = explode("-", $vehicle_reg_hyphen);
                                $rto_code = $reg_array[0] . "-" . $reg_array[1];
                                $new_rc_number = $vehicle_reg_hyphen;
                            }
                        } else {
                            $new_rc_number = 'NEW';
                            $rto_code = $value['rto_code'] ?? null;
                        }
                    }

                    if (!empty($value['rto_code'])) {
                        $rto_code = $value['rto_code'];
                    }

                    RenewalDataMigrationStatus::find($migrationId)->update([
                        'user_product_journey_id' => $user_product_journey->user_product_journey_id ?? null
                    ]);

                    $skipICVahan = config('SKIP_IC_VAHAN_IN_RENEWAL_MIGRATION', 'N') == 'Y';
                    $skipICVahan = $skipICVahan && ($value['is_vahan_ic_hit'] ?? '') == 'N';

                    if (($value['action'] ?? '') == 'migrate' &&
                        !$skipICVahan && empty($policy_details) &&
                        !($value['partial_journey'] ?? false)
                    ) {
                        if (config('constants.brokerConstant.ENABLE_RENEWAL_ATTEMPT_LOGIC') == 'Y') {
                            RenewalDataAttemptJob::dispatch($user_product_journey->user_product_journey_id, $value, $migrationId)
                            ->onQueue(config('RENEWAL_MIGRATION_QUEUE'));
                            continue;
                        }
                        $result = self::PrepareRenewalData($migrationId, $user_product_journey->user_product_journey_id, $value);
                        if (!($result['status'] ?? false)) {
                            $status = 'Failed';
                            RenewalDataMigrationStatus::find($migrationId)->update([
                                'status' => $status,
                                'user_product_journey_id' => $user_product_journey->user_product_journey_id ?? null
                            ]);
                            //DB::commit();
                            continue;
                        }
                    }
                    //DB::beginTransaction();

                    $enquiry_id = $user_product_journey->user_product_journey_id;
                    $current_journey = $user_product_journey;

                    $new_policy_details = null;
                    if (!($value['partial_journey'] ?? false)) {
                        $new_policy_details = PolicyDetails::where('policy_number', $value['previous_policy_number'])
                            ->whereNotNull('policy_number')
                            ->select('policy_number', 'proposal_id')
                            ->first();
                    }

                    //if policy number received from vahan/ic is exists in DB, then update the details
                    if (empty($policy_details) && !empty($new_policy_details) && ($value['action'] ?? '') == 'migrate') {

                        $existing_journey = UserProposal::with(['journey_stage'])->find($new_policy_details->proposal_id)
                        ->user_product_journey;

                        $current_journey->old_journey_id = $existing_journey->user_product_journey_id;
                        $current_journey->save();
                        

                        $enquiry_id = $existing_journey->user_product_journey_id;
                        $user_product_journey = $existing_journey;

                        $old_journey_stage = $existing_journey->journey_stage;

                        if (($old_journey_stage->stage ?? '') != STAGE_NAMES['POLICY_ISSUED']) {
                            $updation_data = [
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ];
                            self::prepareJourneyStageUpdationLogs($updation_data, $old_journey_stage, $value, $migrationId, $user_product_journey->user_product_journey_id);
                        }
                    }

                    if (!empty($value['seller_code'])) {
                        $sellerType = '';
                        switch ($value['seller_type']) {
                            case 'E':
                                $sellerType = 'employee';
                                break;
                            case 'P':
                                $sellerType = 'pos';
                                break;
                            case 'Partner':
                                $sellerType = 'partner';
                                break;
                        }
                        $userRequest =  [
                            $sellerType => ['seller_code' => [$value['seller_code']]]
                        ];

                        $response = httpRequestNormal(
                            config('constants.brokerConstant.FETCH_USER_API'),
                            'POST',
                            $userRequest,
                            [],
                            [],
                            [],
                            true
                        )['response'];
                        $agentData = [];
                        $response = $response[$sellerType][$value['seller_code']] ?? null;
                        if (($response['status'] ?? null) == true) {
                            $agentResponse = $response['data'];
                            $agentData = [
                                'seller_type'   => $agentResponse['seller_type'] ?? NULL,
                                'agent_id'      => $agentResponse['seller_id'] ?? NULL,
                                'user_name'     => $value['seller_code'] ?? NULL,
                                'agent_name'    => $agentResponse['seller_name'] ?? NULL,
                                'agent_mobile'  => $agentResponse['mobile'] ?? NULL,
                                'agent_email'   => $agentResponse['email'] ?? NULL,
                                // 'unique_number' => $agentResponse['user_name'] ?? NULL,
                                'aadhar_no'     => $agentResponse['seller_aadhar_no'] ?? NULL,
                                'pan_no'        => $agentResponse['seller_pan_no'] ?? NULL,
                                'user_id'       => $agentResponse['seller_id'] ?? NULL,
                                'pos_key_account_manager' => (isset($agentResponse['seller_type']) && $agentResponse['seller_type'] == "P") ? ($agentResponse["rm_id"] ?? NULL) : ($agentResponse['user_id'] ?? NULL),
                                'source'       => "RENEWAL_DATA_UPLOAD",
                            ];
                        }
                    } else {
                        $agentData = [
                            'seller_type'   => $value['seller_type'] ?? NULL,
                            'agent_id'      => $value['seller_id'] ?? NULL,
                            'user_name'     => $value['seller_username'] ?? NULL,
                            'agent_name'    => $value['seller_name'] ?? NULL,
                            'agent_mobile'  => $value['seller_mobile'] ?? NULL,
                            'agent_email'   => $value['seller_email'] ?? NULL,
                            // 'unique_number' => $value['seller_type'] ?? NULL,
                            'aadhar_no'     => $value['seller_aadhar_no'] ?? NULL,
                            'pan_no'        => $value['seller_pan_no'] ?? NULL,
                            'user_id' =>       NULL,
                            'pos_key_account_manager' => (isset($value['seller_type']) && $value['seller_type'] == "P") ? ($value["rm_id"] ?? NULL) : ($value['user_id'] ?? NULL),
                            'source'       => "RENEWAL_DATA_UPLOAD",
                        ];
                    }

                    //if reference code id ther store is directly
                    if (!empty($value['reference_code'])) {
                            ProposalExtraFields::updateOrCreate(["enquiry_id" => $enquiry_id], [
                                "enquiry_id" => $enquiry_id,
                                "reference_code" => $value['reference_code']
                            ]);
                        }

                                       
                    if (!empty($agentData)) {
                        $oldAgentDetails = CvAgentMapping::where('user_product_journey_id', $enquiry_id)
                        ->first();
                        if (!empty($value['idv'])) {

                            if ((int)$value['idv'] >= 5000000 && in_array($agentData['seller_type'], ['P', 'Partner']) && config('POS_TO_PARTNER_ALLOW_FIFTY_lAKH_IDV') == 'Y'  && config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y') {

                                if ($agentData['seller_type'] == "P") {


                                    $partnerDataUpdate = PosToPartnerUtility::posToPartnerFiftyLakhIdv((object)$agentData, $enquiry_id, true);

                                    if ($partnerDataUpdate['status'] && $partnerDataUpdate['msg'] == "offline renewal data upload") {
                                        if (!empty($partnerDataUpdate['data'])) {
                                            $partnerDataUpdate = $partnerDataUpdate['data'];

                                            $agentData['seller_type'] = $partnerDataUpdate['seller_type'];
                                            $agentData['user_name'] = $partnerDataUpdate['user_name'];
                                            $agentData['agent_id'] = $partnerDataUpdate['agent_id'];

                                            ProposalExtraFields::updateOrCreate(["enquiry_id" => $enquiry_id], [
                                                "enquiry_id" => $enquiry_id,
                                                "reference_code" => $partnerDataUpdate['reference_code']
                                            ]);
                                        }
                                    }
                                } elseif ($agentData['seller_type'] == "Partner") {

                                    $getPartnerData = PosToPartnerUtility::PartnerFiftyLakhIdv((object)$agentData);

                                    if (isset($getPartnerData['status'])) {

                                        if (!empty($getPartnerData['data'])) {

                                            $getPartnerData = $getPartnerData['data'];



                                            ProposalExtraFields::updateOrCreate(["enquiry_id" => $enquiry_id], [
                                                "enquiry_id" => $enquiry_id,
                                                "reference_code" => $getPartnerData['reference_code']
                                            ]);
                                        }
                                    }
                                }
                            }
                            $agentData = array_filter($agentData, function ($item) {
                                return !empty($item);
                            });

                            self::prepareAgentUpdationLogs($agentData, $oldAgentDetails, $value, $migrationId, $enquiry_id);
                        }
                    }


                    $business_type = !empty($value['business_type']) ? $value['business_type'] : 'rollover';

                    if (isset($value['business_type']) && $value['business_type'] == 'New') {
                        $business_type = 'newbusiness';
                    }

                    $policy_type = NULL;


                    if (($value["prev_policy_type"] ?? '') == "OD") {
                        $policy_type = "own_damage";
                    } else if (($value["prev_policy_type"] ?? '') == "COMPREHENSIVE") {
                        $policy_type = "comprehensive";
                    } else if (($value["prev_policy_type"] ?? '') == "TP") {
                        $policy_type = "third_party";
                    } else if (($value["prev_policy_type"] ?? '') == "1OD+3TP") {
                        $policy_type = "comprehensive";
                    } else if (($value["prev_policy_type"] ?? '') == "1OD+5TP") {
                        $policy_type = "comprehensive";
                    } else if (($value["prev_policy_type"] ?? '') == "3OD+3TP") {
                        $policy_type = "comprehensive";
                    } else if (($value["prev_policy_type"] ?? '') == "5OD+5TP") {
                        $policy_type = "comprehensive";
                    } else {
                        $policy_type = "comprehensive";
                    }


                    $previous_ncb = $value['old_ncb'] ?? null;
                    $applicable_ncb = (isset($value['previous_ncb'])) ? ( (int) $value['previous_ncb'] ?? NULL ) : NULL;
                    $idv = (int) ($value['idv'] ?? NULL);

                    if(empty($value['owner_type'] ?? null)) {
                        $value['owner_type'] = 'individual';
                    }

                    $gender = $gender_name = $dob = null;
                    
                    if (strtolower($value['owner_type']) == 'individual') {
                        $vehicle_owner_type = 'I';
                        if (isset($value['gender'])) {
                            $gender = ($value['gender'] == 'M') ? "Male" : ($value['gender'] == 'F' ? 'Female' : NULL);
                            $gender_name = ($value['gender'] == 'M') ? "Male" : ($value['gender'] == 'F' ? 'Female' : NULL);
                        }
                        if (isset($value["dob"])) {
                            $dob = ($value["dob"] != '') ? Carbon::parse($value["dob"])->format('d-m-Y') : NULL;
                        }
                        $nominee_name = $value["nominee_name"] ?? NULL;
                        $nominee_age = $value["nominee_age"] ?? NULL;
                        $nominee_relationship = $value["relationship_with_nominee"] ?? NULL;
                        $nominee_dob = (isset($value["nominee_dob"])) ? (($value["nominee_dob"] != '') ? Carbon::parse($value["nominee_dob"])->format('d-m-Y') : NULL) : NULL;
                    } else if (strtolower($value['owner_type']) == 'company') {
                        $vehicle_owner_type = 'C';
                        $gender = NULL;
                        $gender_name = NULL;
                        $dob = NULL;
                        $nominee_name = NULL;
                        $nominee_age = NULL;
                        $nominee_relationship = NULL;
                        $nominee_dob = NULL;
                    }

                    $fuelType = null;

                    if (empty($value['version_id']) && !empty($value['insurer_vehicle_model_code'])) {
                        self::getFyntuneVersionId($value, $value['insurer_vehicle_model_code']);
                    }
                    if (!empty($value['version_id'])) {
                        $mmvDetails = get_fyntune_mmv_details($user_product_journey->product_sub_type_id, $value['version_id']);
                        $mmvDetails = $mmvDetails['data'] ?? [];
                        if (!empty($mmvDetails)) {
                            $fuelType = $mmvDetails['version']['fuel_type'] ?? null;
                        }
                    }

                    $vehicle_register_date = $value['registration_date'] ?? '';
                    $rto_city = $value['vehicle_registration_city'] ?? null;

                    if (empty($rto_city) && !empty($rto_code)) {
                        $rto_city = self::getRtoCityName($rto_code);
                    }

                    # Corporate Vehicle Store.
                    $corporateVehicleData = [
                        'version_id'                    => $value['version_id'] ?? NULL,
                        'business_type'                 => $business_type,
                        'policy_type'                   => $policy_type,
                        'vehicle_register_date'         => ($vehicle_register_date != '') ? Carbon::parse($vehicle_register_date)->format('d-m-Y') : NULL,
                        'previous_policy_expiry_date'   => NULL,
                        'vehicle_registration_no'       => $new_rc_number,
                        'previous_policy_type'          => NULL,
                        'previous_insurer'              => NULL,
                        'fuel_type'                     => $fuelType,
                        'manufacture_year'              => $value['vehicle_manufacture_year'] ?? NULL,
                        'rto_code'                      => $rto_code ?? NULL,
                        'rto_city'                      => $rto_city,
                        'vehicle_owner_type'            => $vehicle_owner_type ?? NULL,
                        'is_claim'                      => (isset($value['previous_claim_status'])) ? (($value['previous_claim_status']) == 'Y' ? 'Y' : 'N'): 'N',
                        'previous_ncb'                  => $previous_ncb,
                        'applicable_ncb'                => $applicable_ncb,
                        'version_name'                  => NULL,
                        'is_renewal'                    => 'N',
                        'previous_insurer'              => NULL,
                        'previous_insurer_code'         => NULL,
                        'insurance_company_id'          => NULL,
                        'product_id'                    => $product_sub_type_id["product_sub_type_id"] ?? NULL,
                        'created_on'                    => $transaction_date
                    ];
                    $corporateVehicleData = array_filter($corporateVehicleData, function ($item) {
                        return !empty($item);
                    });
                    $user_product_journey->corporate_vehicles_quote_request()->updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiry_id
                        ], $corporateVehicleData
                    );

                    $selected_addons = [];

                    if (isset($value['engine_protector']) && !empty($value['engine_protector']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Engine Protector';
                    }
                    if (isset($value['key_replacement']) && !empty($value['key_replacement']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Key Replacement';
                    }
                    if (isset($value['zero_dep']) && !empty($value['zero_dep'] && $value['zero_dep'] != 'N') /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Zero Depreciation';
                    }
                    if (isset($value['loss_of_personal_belonging']) && !empty($value['loss_of_personal_belonging']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Loss of Personal Belongings';
                    }
                    if (isset($value['return_to_invoice']) && !empty($value['return_to_invoice']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Return To Invoice';
                    }
                    if (isset($value['tyre_secure']) && !empty($value['tyre_secure']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Tyre Secure';
                    }
                    if (isset($value['consumable']) && !empty($value['consumable']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Consumable';
                    }
                    if (isset($value['rsa']) && !empty($value['rsa']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Road Side Assistance';
                    }
                    if (isset($value['ncb_protection']) && !empty($value['ncb_protection']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'NCB Protection';
                    }
                    if (isset($value['emergency_medical_expenses']) && !empty($value['emergency_medical_expenses']) /* == "Selected" */) {
                        $selected_addons[]['name'] = 'Emergency Medical Expenses';
                    }

                    $selected_accessories = [];

                    if (isset($value['not-electrical']) && !empty($value['not-electrical'] && !empty($value['not-electrical_si_amount'])) /* == "Selected" */) {
                        // $selected_accessories[]['name'] = 'Non-Electrical Accessories';
                        // $selected_accessories[]['sumInsured'] = $value['not-electrical_si_amount'] ?? 0;
                        $selected_accessories[] = [
                            'name' => 'Non-Electrical Accessories',
                            'sumInsured' => $value['not-electrical_si_amount'] ?? 0
                        ];
                    }
                    if (isset($value['electrical']) && !empty($value['electrical'] && !empty($value['electrical_si_amount'])) /* == "Selected" */) {
                        // $selected_accessories[]['name'] = 'Electrical Accessories';
                        // $selected_accessories[]['sumInsured'] = $value['electrical_si_amount'] ?? 0;
                        $selected_accessories[] = [
                            'name' => 'Electrical Accessories',
                            'sumInsured' => $value['electrical_si_amount'] ?? 0
                        ];
                    }
                    if (isset($value['external_bifuel_cng_lpg']) && !empty($value['external_bifuel_cng_lpg'] && !empty($value['external_bifuel_cng_lpg_si_amount'])) /* == "Selected" */) {
                        // $selected_accessories[]['name'] = 'External Bi-Fuel Kit CNG/LPG';
                        // $selected_accessories[]['sumInsured'] = $value['external_bifuel_cng_lpg_si_amount'] ?? 0;
                        $selected_accessories[] = [
                            'name' => 'External Bi-Fuel Kit CNG/LPG',
                            'sumInsured' => $value['external_bifuel_cng_lpg_si_amount'] ?? 0
                        ];
                    }

                    $selected_additional_covers = [];

                    if (isset($value['pa_cover_for_additional_paid_driver']) && !empty($value['pa_cover_for_additional_paid_driver'] && !empty($value['pa_cover_for_additional_paid_driver_si_amount'])) /* == "Selected" */) {
                        // $selected_additional_covers[]['name'] = 'PA cover for additional paid driver';
                        // $selected_additional_covers[]['sumInsured'] = $value['pa_cover_for_additional_paid_driver_si_amount'] ?? 0;
                        $selected_additional_covers[] = [
                            'name' => 'PA cover for additional paid driver',
                            'sumInsured' => $value['pa_cover_for_additional_paid_driver_si_amount'] ?? 0
                        ];
                    }
                    if (isset($value['unnammed_passenger_pa_cover']) && !empty($value['unnammed_passenger_pa_cover'] && !empty($value['unnammed_passenger_pa_cover_si_amount'])) /* == "Selected" */) {
                        // $selected_additional_covers[]['name'] = 'Unnamed Passenger PA Cover';
                        // $selected_additional_covers[]['sumInsured'] = $value['unnammed_passenger_pa_cover_si_amount'] ?? 0;
                        $selected_additional_covers[] = [
                            'name' => 'Unnamed Passenger PA Cover',
                            'sumInsured' => $value['unnammed_passenger_pa_cover_si_amount'] ?? 0
                        ];
                    }
                    if (isset($value['ll_paid_driver']) && !empty($value['ll_paid_driver']) /* == "Selected" */) {
                        // $selected_additional_covers[]['name'] = 'LL paid driver';
                        // $selected_additional_covers[]['sumInsured'] = $value['ll_paid_driver_si_amount'] ?? 0;
                        $selected_additional_covers[] = [
                            'name' => 'LL paid driver',
                            'sumInsured' => $value['ll_paid_driver_si_amount'] ?? 0
                        ];
                    }
                    if (isset($value['geogarphical_extension']) && !empty($value['geogarphical_extension']) /* == "Selected" */) {
                        // $selected_additional_covers[]['name'] = 'Geographical Extension';
                        // $selected_additional_covers[]['sumInsured'] = $value['geogarphical_extension_si_amount'] ?? 0;
                        $selected_additional_covers[] = [
                            'name' => 'Geographical Extension',
                            'countries' => [
                                false,
                                false,
                                false,
                                false,
                                false,
                                false
                            ]
                        ];
                    }


                    if (isset($value['cpa_amount']) &&  (int) $value['cpa_amount'] > 0) {
                        $cpa['name'] = 'Compulsory Personal Accident';
                    } else {
                        $cpa['reason'] = (isset($value['cpa_opt-out_reason']) &&  $value['cpa_opt-out_reason'] != "") ? $value['cpa_opt-out_reason'] : "I do not have a valid driving license.";
                    }

                    //discounts
                    $discounts = [];

                    if (isset($value['tpp_discount']) &&  (int) $value['tpp_discount'] > 0) {
                        $discounts[] = [
                            'name' => 'TPPD Cover'
                        ];
                    }

                    if (isset($value['anti_theft']) &&  (int) $value['anti_theft'] > 0) {
                        $discounts[] = [
                            'name' => 'anti-theft device'
                        ];
                    }

                    if (!empty($value['voluntary_excess'] ?? null) && !empty($value['voluntary_excess_si'])) {
                        $discounts[] = [
                            'name' => 'voluntary_insurer_discounts',
                            'sumInsured' => $value['voluntary_excess_si']
                        ];
                    }

                    $addonData = [
                        'addons'                        => ($selected_addons),
                        'applicable_addons'             => ($selected_addons),
                        'accessories'                  => ($selected_accessories),
                        'additional_covers'            => ($selected_additional_covers),
                        'discounts'                    => ($discounts),
                        'compulsory_personal_accident'  => ([$cpa]),
                        'created_at'                    => $transaction_date,
                        'updated_at'                    => $transaction_date
                    ];

                    $addonData = array_filter($addonData, function ($item) {
                        return !empty($item);
                    });
                    $user_product_journey->addons()->updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiry_id
                        ], $addonData
                    );

                    $od_premium = !empty($value['od_premium']) ? $value['od_premium'] : 0;
                    if (empty($od_premium) && !empty($value['basic_od'])) {
                        $od_premium = $value['basic_od'];
                    }

                    $tp_premium = !empty($value['tp_premium']) ? $value['tp_premium'] : 0;
                    if (empty($tp_premium) && !empty($value['basic_tp'])) {
                        $tp_premium = $value['basic_tp'];
                    }

                    $premium_json = [
                        'mmvDetail' => [
                            'fuelType' => $value['vehicle_fuel_type'] ?? null,
                            'cubicCapacity' => $value['cubic_capacity'] ?? null,
                            'grossVehicleWeight' => $value['vehicle_gvw'] ?? null,
                            'seatingCapacity' => $value['vehicle_seating_capacity'] ?? null,
                            'manfName' => $value['manfacture_name'] ?? null,
                            'modelName' => $value['model_name'] ?? null,
                            'versionName' => $value['version_name'] ?? null,
                        ]
                    ];

                    if (!empty($value['basic_tp'])) {
                        $premium_json['tppdPremiumAmount'] = $value['basic_tp'];
                    }

                    if (!empty($value['basic_od'])) {
                        $premium_json['basicPremium'] = $value['basic_od'];
                    }

                    $premium_json['applicableAddons'] = [];
                    if (!empty($value['zero_dep']) && $value['zero_dep'] != 'N') {
                        $premium_json['applicableAddons'][] = 'zeroDepreciation';
                    }

                    $premium_json['mmvDetail'] = array_filter($premium_json['mmvDetail'], function ($value) {
                        return $value !== null;
                    });

                    if (
                        !empty($user_product_journey->quote_log) &&
                        !empty($user_product_journey->quote_log->premium_json)
                    ) {
                        $old_premium_json = $user_product_journey->quote_log->premium_json;
                        if (!empty($old_premium_json)) {
                            $premium_json = array_replace_recursive($old_premium_json, $premium_json);
                        }
                    }

                    if (
                        !empty($premium_json['applicableAddons']) && ($value['zero_dep'] ?? 'Y') == 'N'
                        && ($index = array_search('zeroDepreciation', $premium_json['applicableAddons'])) !== false
                    ) {
                        array_splice($premium_json['applicableAddons'], $index, 1);
                    }

                    $company_details = null;
                    if (isset($value['company_alias'])) {
                        $company_details = MasterCompany::when(!empty($value['company_alias']), fn ($builder) => $builder->where('company_alias', $value['company_alias']))->first();
                    } else {
                        $company_details = MasterCompany::when(!empty($value['previous_insurer_company']), fn ($builder) => $builder->where('company_alias', $value['previous_insurer_company']))->first();
                    }

                    $quoteLogData = [
                        'product_sub_type_id'   => $product_sub_type_id['product_sub_type_id'] ?? null,
                        'ic_id'                 => $company_details?->company_id,
                        'ic_alias'              => $company_details?->company_alias,
                        'od_premium'            => $od_premium,
                        'tp_premium'            => $tp_premium,
                        'service_tax'           => $value['tax_amount'] ?? 0,
                        'addon_premium'         => $value['addon_premium'] ?? 0,
                        'quote_data'            => '',
                        'premium_json'          => $premium_json,
                        'idv'                   => $idv,
                        'updated_at'            => $transaction_date,
                        'searched_at'           => $transaction_date
                    ];
                    // $quoteLogData = array_filter($quoteLogData, function ($item) {
                    //     return !empty($item);
                    // });
                    $user_product_journey->quote_log()->updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiry_id
                        ],
                        $quoteLogData
                    );

                    $title_array = ['Mr.', 'Ms.', 'Mrs.', 'M/s', 'Dr.'];
                    $full_name = trim($value['full_name'] ?? NULL);
                    $title = NULL;

                    if (isset($value['full_name'])) {
                        foreach ($title_array as $t) {
                            if (str_starts_with($value['full_name'], $t)) {
                                $title = $t;
                                $full_name = trim(str_replace($t, '', $value['full_name']));
                                break;
                            }
                        }
                    }

                    $proposal_data = [
                        'title' => $title,
                        'first_name' => $full_name,
                        'last_name' => NULL,
                        'email' => Str::lower($value['email_address'] ?? null),
                        'office_email' => Str::lower($value['email_address'] ?? null),
                        'mobile_number' => $value['mobile_no'] ?? null,
                        'dob' => $dob,
                        'gender' => $gender,
                        'gender_name' => $gender_name,
                        "pan_number"    => $value['pan_card'] ?? NULL,
                        "gst_number" => $value['gst_no'] ?? NULL,
                        "address_line1" => $value['communication_address'] ?? NULL,
                        "pincode"       => (isset($value['communication_pincode'])) ? ( ($value['communication_pincode'] != '') ? $value['communication_pincode'] : NULL ) : NULL,
                        "state"         => $value['communication_state'] ?? NULL,
                        "city"          => $value['communication_city'] ?? NULL,
                        "rto_location" => $rto_code,
                        "is_car_registration_address_same" => 0,
                        "car_registration_address1" => $value['vehicle_registration_address'] ?? NULL,
                        "car_registration_pincode" => $value['vehicle_registration_pincode'] ?? NULL,
                        "car_registration_state" => $value['vehicle_registration_state'] ?? NULL,
                        "car_registration_city" => $value['vehicle_registration_city'] ?? NULL,
                        "vehicale_registration_number" => $new_rc_number,
                        "vehicle_manf_year" => $value['vehicle_manufacture_year'] ?? NULL,
                        "engine_number"     => $value['engine_no'] ?? NULL,
                        "chassis_number"    => $value['chassis_no'] ?? NULL,
                        "is_vehicle_finance" => (isset($value['is_financed']) && $value['is_financed'] == "Yes") ? 1 : 0,
                        "financer_agreement_type" => $value['financier_agreement_type'] ?? NULL,
                        "hypothecation_city" => $value['hypothecation_city'] ?? NULL,
                        "name_of_financer" => $value['financier_name'] ?? NULL,
                        "previous_insurance_company" => NULL,
                        "previous_policy_number" => $value['old_policy_number'] ?? null,
                        "nominee_name" => $value["nominee_name"] ?? NULL,
                        "nominee_age" => $value["nominee_age"] ?? NULL,
                        "nominee_relationship" => $value["relationship_with_nominee"] ?? NULL,
                        "nominee_dob" => (isset($value["nominee_dob"])) ? ( ($value["nominee_dob"] != '' && $value["nominee_dob"] != "NULL") ? Carbon::parse($value["nominee_dob"])->format('Y-m-d') : NULL ) : NULL,
                        "policy_start_date" => (isset($value["previous_policy_start_date"]) && $value["previous_policy_start_date"] != '') ? Carbon::parse($value["previous_policy_start_date"])->format('d-m-Y') : NULL,
                        "policy_end_date" => (isset($value["previous_policy_end_date"])  && $value["previous_policy_end_date"] != '') ? Carbon::parse($value["previous_policy_end_date"])->format('d-m-Y') : NULL,
                        "tp_start_date" => (isset($value["previous_tp_start_date"]) && $value["previous_tp_start_date"] != '') ? Carbon::parse($value["previous_tp_start_date"])->format('d-m-Y') : NULL,
                        "tp_end_date" => (isset($value["previous_tp_end_date"])  && $value["previous_tp_end_date"] != '') ? Carbon::parse($value["previous_tp_end_date"])->format('d-m-Y') : NULL,
                        "tp_insurance_company" => $value["previous_tp_insurer_company"] ?? NULL,
                        "tp_insurance_number" => $value["previous_tp_policy_number"] ?? NULL,
                        "prev_policy_expiry_date" => NULL,
                        "proposal_no" => $value["proposal_no"] ?? NULL,
                        "od_premium" => $od_premium,
                        "tp_premium" => $tp_premium,
                        "cpa_premium" => !empty($value['cpa_amount']) ? $value['cpa_amount'] : null,
                        "total_premium" => $value["base_premium"] ?? NULL,
                        "addon_premium" => $value['addon_premium'] ?? null,
                        "service_tax_amount" => $value["tax_amount"] ?? NULL,
                        "final_payable_amount" => $value["premium_amount"] ?? NULL,
                        "total_discount" => $value["discount_amount"]  ?? NULL,
                        "owner_type" => $vehicle_owner_type,
                        "occupation"        => $value['occupation'] ?? NULL,
                        "occupation_name"        => $value['occupation'] ?? NULL,
                        "insurance_company_name" => $company_details?->company_name,
                        "ic_name" => $company_details?->company_name,
                        'ic_id' => $company_details?->company_id,
                        'idv'   => $idv,
                        "cpa_start_date" => (isset($value["cpa_policy_start_date"]) && $value["cpa_policy_start_date"] != '') ? Carbon::parse($value["cpa_policy_start_date"])->format('d-m-Y') : '',
                        "cpa_end_date" => (isset($value["cpa_policy_end_date"])  && $value["cpa_policy_end_date"] != '') ? Carbon::parse($value["cpa_policy_end_date"])->format('d-m-Y') : '',
                        "previous_ncb" => $previous_ncb,
                        "applicable_ncb" => $applicable_ncb,
                        'created_date'          => $transaction_date,
                        'proposal_date'         => $transaction_date,
                    ];



                    if ($vehicle_owner_type == 'I') {
                        $nominee = [
                            'nomineeName'             => $nominee_name,
                            'nomineeAge'              => $nominee_age,
                            'nomineeRelationship'     => $nominee_relationship,
                            'relationship_with_owner' => $nominee_relationship
                        ];

                        if (!empty($proposal_data['nominee_dob'])) {
                            $nominee['nomineeDob'] = $proposal_data['nominee_dob'];
                        }
                    } else {
                        $nominee = [];
                    }

                    if ($new_rc_number != '' && strtoupper($new_rc_number) != "NEW") {
                        $full_reg_number = $new_rc_number;
                        $reg = explode('-', $new_rc_number);
                        $reg1 = $reg[0] . '-' . $reg[1];
                        $reg2 = $reg[2] ?? NULL;
                        $reg3 = $reg[3] ?? NULL;
                    } else {
                        $reg1 = $rto_code;
                        $reg2 = NULL;
                        $reg3 = NULL;
                    }

                    $additinal_details = [
                        'owner'     => [
                            "occupation"        => $value['occupation'] ?? NULL,
                            "maritalStatus"     => $value['marital_status'] ?? NULL,
                            "lastName"          => NULL,
                            "firstName"         => $full_name,
                            "fullName"          => $full_name,
                            "gender"            => $gender,
                            "dob"               => $dob,
                            "mobileNumber"      => $value['mobile_no'] ?? NULL,
                            "email"             => Str::lower($value['email_address'] ?? null),
                            "panNumber"         =>  $value['pan_card'] ?? NULL,
                            "gstNumber"         =>  $value['gst_no'] ?? NULL,
                            "stateId"           =>  $value['communication_state'] ?? NULL,
                            "cityId"            =>  $value['communication_city'] ?? NULL,
                            "state"             =>  $value['communication_state'] ?? NULL,
                            "city"              =>  $value['communication_city'] ?? NULL,
                            "addressLine1"      =>  $value['communication_address'] ?? NULL,
                            "pincode"           =>  $value['communication_pincode'] ?? NULL,
                            "officeEmail"       =>  Str::lower($value['email_address'] ?? null),
                            "prevOwnerType"     =>  $vehicle_owner_type,
                            "address"           =>  $value['communication_address'] ?? NULL,
                            "genderName"        =>  $gender_name,
                            "occupationName"    =>  $value['occupation'] ?? NULL,
                        ],
                        'nominee'   => $nominee,
                        'vehicle'   => [
                            "regNo1"                        => $reg1,
                            "regNo2"                        => $reg2,
                            "regNo3"                        => $reg3,
                            "chassisNumber"                 => $value['chassis_no'] ?? NULL,
                            "vehicleManfYear"               => $value['vehicle_manufacture_year'] ?? NULL,
                            "engineNumber"                  => $value['engine_no'] ?? null,
                            "vehicaleRegistrationNumber"    => $full_reg_number ?? $reg1,
                            "vehicleColor"                  => $value['vehicle_colour'] ?? NULL,
                            "isValidPuc"                    => true,
                            "isVehicleFinance"              => (isset($value['is_financed']) && $value['is_financed'] == "Yes") ? true : false,
                            "isCarRegistrationAddressSame"  => true,
                            "rtoLocation"                   => $reg1,
                            "registrationDate"              => $vehicle_register_date,
                            "financer_agreement_type"       => $value['financier_agreement_type'] ?? NULL,
                            "hypothecation_city"            => $value['hypothecation_city'] ?? NULL
                        ],

                        'prepolicy' => [
                            "previousInsuranceCompany"  => NULL,
                            "InsuranceCompanyName"      => NULL,
                            "previousPolicyExpiryDate"  => NULL,
                            "previousPolicyNumber"      => NULL,
                            "isClaim"                   => $value['previous_claim_status'] ?? "N",
                            "applicableNcb"             => $applicable_ncb,
                            "previousNcb"               => $previous_ncb,
                            "prevPolicyExpiryDate"      => NULL
                        ]
                    ];

                    if (!empty($user_product_journey->user_proposal->additional_details)) {
                        $additional_details_old = json_decode($user_product_journey->user_proposal->additional_details, true);
                        if (!empty($additional_details_old)) {
                            self::updateDetails($additional_details_old, $additinal_details);
                            $additinal_details = $additional_details_old;
                        }
                    }

                    $proposal_data['additional_details'] = json_encode($additinal_details);

                    $proposal_data = array_filter($proposal_data, function ($item) {
                        return !empty($item);
                    });

                    if (!empty($proposal_data['first_name'])) {
                        $proposal_data['last_name'] = null;
                    }

                    if (isset($applicable_ncb) && strlen($applicable_ncb) > 0) {
                        $proposal_data["applicable_ncb"] = $applicable_ncb;
                    }
        
                    if (isset($previous_ncb) && strlen($previous_ncb) > 0) {
                        $proposal_data["previous_ncb"] = $previous_ncb;
                    }

                    $proposal_data["od_premium"] = $od_premium;
                    $proposal_data["tp_premium"] = $tp_premium;
                    $proposal_data["cpa_premium"] = !empty($value['cpa_amount']) ? $value['cpa_amount'] : null;
                    $proposal_data["total_premium"] = $value["base_premium"] ?? null;
                    $proposal_data["addon_premium"] = $value['addon_premium'] ?? null;
                    $proposal_data["service_tax_amount"] = $value["tax_amount"] ?? null;
                    $proposal_data["final_payable_amount"] = $value["premium_amount"] ?? null;
                    $proposal_data["total_discount"] = $value["discount_amount"]  ?? null;

                    $proposal = $user_product_journey->user_proposal()->updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiry_id
                        ],
                        $proposal_data
                    );

                    # Store Journey Stage

                    $journeyData = [
                        'ic_id'         => $company_details?->company_id,
                        'proposal_id'   => $proposal->user_proposal_id,
                        'stage'         => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                        'created_at'    => $transaction_date,
                        'updated_at'    => $transaction_date
                    ];

                    $journeyData = array_filter($journeyData, function ($item) {
                        return !empty($item);
                    });

                    $old_updated_at = null;
                    if (!empty($user_product_journey->journey_stage->updated_at)) {
                        $old_updated_at = $user_product_journey->journey_stage->updated_at;
                    }

                    # Store Payment Response.
                    $paymentData = [
                        'ic_id'             => $company_details?->company_id,
                        'user_proposal_id'  => $proposal->user_proposal_id,
                        'proposal_no'       => $proposal->proposal_no,
                        'order_id'          => $proposal->proposal_no,
                        'active'            => 1,
                        'created_at'        => $transaction_date,
                        'updated_at'        => $transaction_date,
                        'status'            => STAGE_NAMES['PAYMENT_SUCCESS']
                    ];

                    $paymentData = array_filter($paymentData, function ($item) {
                        return !empty($item);
                    });

                    if (!($value['partial_journey'] ?? false)) {
                        $user_product_journey->payment_response()->updateOrCreate(
                            [
                                'user_product_journey_id' => $enquiry_id
                            ],
                            $paymentData
                        );
                    }

                    # Store Policy Details.
                    $policyDetails = [
                        'policy_number' => $value['previous_policy_number'] ?? null,
                        'status'        => 'SUCCESS',
                        'created_on'    => $transaction_date
                    ];

                    if (!empty($value['policy_doc_path'])) {
                        $policyDetails['pdf_url'] = $value['policy_doc_path']; 
                        $policyDetails['rehit_source'] = "OFFLINE_DATA_UPLOAD"; 
                    }

                    $policyDetails = array_filter($policyDetails, function ($item) {
                        return !empty($item);
                    });

                    if (!($value['partial_journey'] ?? false)) {
                        $data = $proposal->policy_details()->updateOrCreate(
                            [
                                'proposal_id' => $proposal->user_proposal_id
                            ], $policyDetails
                        );
                    }

                    $journeyData['stage'] = STAGE_NAMES['POLICY_ISSUED'];

                    if ($value['partial_journey'] ?? false) {
                        $journeyData['stage'] = 'Lead Generation';
                    }
                    

                    $user_product_journey->journey_stage()->updateOrCreate(
                        [
                            'user_product_journey_id' => $enquiry_id
                        ],
                        $journeyData
                    );

                    //updated_at is used by dashboard to show the policy in issued section,
                    //so the updated_at should be old timestamp not the current timestamp

                    if (!empty($old_updated_at)) {
                        JourneyStage::where('user_product_journey_id', $enquiry_id)
                        ->update(['updated_at' => $old_updated_at]);
                    }

                    RenewalDataMigrationStatus::find($migrationId)
                    ->update([
                        'status' => 'Success'
                    ]);

                    self::generateUserId($enquiry_id, $value);

                } catch (\Exception $e) {
                    //DB::rollBack();
                    Log::error($e);

                    if ($migrationId ?? false) {
                        RenewalDataMigrationStatus::find($migrationId)
                        ->update([
                            'status' => 'Failed'
                        ]);
                    }
                    continue;
                }
            }
            # If Everything is OK then Delete File.
            Storage::delete($file);
        }
    }

    public static function PrepareRenewalData($migrationId, $enquiryId, &$value)
    {
        $attempts = 1;
        $oldValue = $value;
        $className = "\App\Quotes\FetchRenewalData\\";
        $className.=  ucfirst(strtolower($value['product_name']));
        $className.= "\\".($value['company_alias'] ?? $value['previous_insurer_company']);
        if (class_exists($className)) {
            $result = $className::getRenewalData($enquiryId, $value);
            if ($result === false || (empty($value['version_id'] ?? null))) {
                if (!$result) {
                    $value = $oldValue;
                }
                $oldValue = $value;

                if (empty($value['vehicle_registration_number']) || strtoupper($value['vehicle_registration_number'] ?? '') == 'NEW') {
                    return ['status' => false, 'type' => 'vahan'];
                }

                $sectionList = [
                    'car' => '1',
                    'bike' => '2',
                    'cv' => '5',
                    'cv' =>  '6',
                    'cv' =>  '7',
                    'cv' =>  '8',
                    'cv' =>  '10',
                    'cv' => '11',
                    'cv' => '12',
                    'cv' => '4',
                    'cv' => '9',
                    'cv' =>  '13',
                    'cv' => '14',
                    'cv' =>  '15',
                    'cv' =>  '16',
                ];

                $getVehicleDetails = [
                    "enquiryId" => customEncrypt($enquiryId),
                    "registration_no" => getRegisterNumberWithHyphen($value['vehicle_registration_number']),
                    "productSubType" => $sectionList[strtolower($value['product_name'])] ?? '',
                    "section" => strtolower($value['product_name']),
                    "is_renewal" => "N",
                    'vehicleValidation' => 'N'
                ];
                $vehicleRegisterNumber = $getVehicleDetails['registration_no'];
                $vehicleRegisterNumber = explode('-', $vehicleRegisterNumber);
                if (isset($vehicleRegisterNumber[1], $vehicleRegisterNumber[0]) && $vehicleRegisterNumber[0] == 'DL') {
                    $rtoCode = $vehicleRegisterNumber[0] . '-' . $vehicleRegisterNumber[1];
                    $rtoCode = explode('-', RtoCodeWithOrWithoutZero($rtoCode, false));
                    $vehicleRegisterNumber[1] = $rtoCode[1];
                }
                $vehicleRegisterNumber = implode('-', $vehicleRegisterNumber);

                $getVehicleDetails['registration_no'] = $vehicleRegisterNumber;

                $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->first();

                //quote log is neccessary in vahan controller (fastlane)
                if (empty($quoteLog)) {
                    QuoteLog::create([
                        'user_product_journey_id' => $enquiryId
                    ]);
                }

                $totalNumbers = config('RENEWAL_VAHAN_API_ATTEMPTS', 3);

                do {
                    $attempts++;
                    $vehicleRequest = new \Illuminate\Http\Request($getVehicleDetails);
                    $vahanController = new \App\Http\Controllers\VahanService\VahanServiceController();
                    $vahanController = $vahanController->hitVahanService($vehicleRequest);
                    $vahanController = is_object($vahanController) ? $vahanController->original : $vahanController;

                    RenewalDataMigrationStatus::find($migrationId)->update([
                        'attempts' => $attempts
                    ]);

                    if (($vahanController['status'] ?? false)) {

                        $serviceList = $vahanController['servicelist'] ?? [];
                        $service = end($serviceList);
                        $service = is_object($service) ? $service->original ?? $service : $service;
                        $check = self::prepareVahanData($value, $service, $enquiryId);

                        //if version id is not available (from ic api), then call vahan api and append only the vsersion id
                        if ($result) {
                            $oldValue['version_id'] = $value['version_id'];
                            $value = $oldValue;
                        }
                        return $check;
                    }
                    if (empty($vahanController['servicelist'] ?? null)) {
                        return ['status' => false, 'type' => 'vahan'];
                    }
                    $totalNumbers --;
                }  while($totalNumbers != 0);
                return ['status' => false, 'type' => 'vahan'];
            }
            return ['status' => true];
        }
        return ['status' => false];
    }

    public static function prepareVahanData(&$value, $service, $enquiryId = null)
    {

        if (($service['status'] ?? false) && !empty($service['data'] ?? null)) {
            $service = $service['data']['additional_details'] ?? [];
            if (!empty($service)) {
                
                // if ($enquiryId) {
                //     $userProposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                //     if (!empty($userProposal) && !empty($userProposal->previous_policy_number ?? null)) {
                //         $value['previous_policy_number'] = $userProposal->previous_policy_number;
                //     }
                // }
                $value['previous_ncb'] =$value['previous_ncb'] ?? null;
                $value['previous_claim_status'] = $service['isClaim'] ?? $value['previous_claim_status'] ?? null;

                $value['registration_date'] = $service['vehicleRegisterDate'] ?? null;
                $value['vehicle_manufacture_year'] = $service['manufacture_year'] ?? $service['manufactureYear'] ?? '';
                $value['is_financed'] = ($service['financer_sel']['name'] ?? $value['is_financed'] ?? '') == 1 ? 'Yes' : null;
                $value['financier_name'] = $service['financer_sel']['code'] ?? $value['financier_name'] ?? null;
                $value['version_id'] = $service['version'];

                $value['full_name'] = $service['fullName'];
                $value['email_address'] = !empty($service['emailId']) ? $service['emailId'] : ($value['email_address'] ?? null);
                $value['owner_type'] = !empty($service['vehicleOwnerType']) ? ($service['vehicleOwnerType'] == 'I' ? 'individual' : 'company') : ($value['owner_type'] ?? null);
                $value['communication_pincode'] = $service['pincode'];
                $value['communication_address'] = trim($service['address_line1']. ' '.$service['address_line2']. ' '.$service['address_line3']);
                
                $value['nominee_name'] = $value['nominee_name'] ?? null;
                $value['nominee_age'] = $value['nominee_age'] ?? null;
                $value['relationship_with_nominee'] = $value['relationship_with_nominee'] ?? null;
                $value['nominee_dob'] = $value['nominee_dob'] ?? null;

                $value['financier_agreement_type'] = $value['financier_agreement_type'] ?? null;
                $value['engine_no'] = $value['engine_no'] ?? null;

                return ['status' => true];
            }
            
        }
        return ['status' => false, 'type' => 'vahan'];
    }

    public static function prepareAgentUpdationLogs($agentData, $oldAgentDetails, $value, $migrationId, $enquiryId)
    {
        $newAgentDetails = CvAgentMapping::updateOrCreate([
            'user_product_journey_id' => $enquiryId
        ], $agentData);

        $newData = $newAgentDetails->getChanges();
        unset($newData['updated_at']);
        $oldData = [];
        if (!empty($oldAgentDetails)) {
            $oldData = array_intersect_key($oldAgentDetails->getOriginal(), array_flip(array_keys($newData)));
        }

        if (!empty($newData)) {
            $logs = [
                'previous_policy_number' => $value['previous_policy_number'],
                'renewal_data_migration_status_id' => $migrationId,
                'user_product_journey_id' => $enquiryId,
                'vehicle_registration_number' => $value['vehicle_registration_number'] ?? null,
                'type' => 'agent',
                'new_data' => json_encode($newData),
                'old_data' => json_encode($oldData)
            ];
            RenewalUpdationLog::create($logs);
        }
    }

    public static function prepareJourneyStageUpdationLogs($update_data, $old_journey_stage, $value, $migrationId, $enquiryId)
    {
        $old_updated_at = null;

        if (!empty($old_journey_stage->updated_at)) {
            $old_updated_at = $old_journey_stage->updated_at;
        }
        $new_journey_stage = JourneyStage::updateOrCreate([
            'user_product_journey_id' => $enquiryId
        ],$update_data);

        //updated_at is used by dashboard to show the policy in issued section,
        //so the updated_at should be old timestamp not the current timestamp
        
        if (!empty($old_updated_at)) {
            JourneyStage::where('user_product_journey_id', $enquiryId)
            ->update(['updated_at' => $old_updated_at]);
        }

        $new_data = $new_journey_stage->getChanges();
        unset($new_data['updated_at']);
        $old_data = [];
        if (!empty($old_journey_stage)) {
            $old_data = array_intersect_key($old_journey_stage->getOriginal(), array_flip(array_keys($new_data)));
        }

        if (!empty($old_data)) {
            $logs = [
                'previous_policy_number' => $value['previous_policy_number'],
                'renewal_data_migration_status_id' => $migrationId,
                'user_product_journey_id' => $enquiryId,
                'vehicle_registration_number' => $value['vehicle_registration_number'] ?? null,
                'old_data' => json_encode($old_data),
                'new_data' => json_encode($new_data),
                'type' => 'journey_stage'
            ];
            RenewalUpdationLog::create($logs);
        }
    }

    public static function getFyntuneVersionId(&$value, $code)
    {
        try {
            $className = "\App\Quotes\FetchRenewalData\\";
            $className .=  ucfirst(strtolower($value['product_name']));
            $className .= "\\" . ($value['company_alias'] ?? $value['previous_insurer_company']);
            if (class_exists($className)) {
                return $className::getFyntuneVersionId($value, $code);
            }
            return false;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public static function generateUserId($enquiryId, $value)
    {
        try {
            if (
                config('constants.motorConstant.ENABLE_USER_CREATION_IN_RENEWAL_MIGRATION') == 'Y'
                && !empty($value['mobile_no'])
            ) {

                $response = httpRequestNormal(config('constants.motorConstant.BROKER_USER_CREATION_API'), 'POST', [
                    'mobile_no' => $value['mobile_no']
                ]);

                if (!empty($response['response']['user_id'])) {
                    CvAgentMapping::updateOrCreate([
                        'user_product_journey_id' => $enquiryId
                    ], [
                        "user_id" => $response['response']['user_id']
                    ]);
                    return true;
                }
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
        return false;
    }

    public static function getRtoCityName($rto_code)
    {
        $rto_city = MasterRto::select('rto_name')
        ->where('rto_code', $rto_code)
        ->first();

        return $rto_city->rto_name ?? null;
    }

    public static function updateDetails(&$oldDetails, $newDetails) {
        foreach ($newDetails as $key => $value) {
            if (is_array($value) && isset($oldDetails[$key]) && is_array($oldDetails[$key])) {
                self::updateDetails($oldDetails[$key], $value);
            } elseif (!empty($value)) {
                $oldDetails[$key] = $value;
            }
        }
    }

    public static function createEmptyJourney($value)
    {
        $record = ProposalExtraFields::where('upload_secondary_key', $value['secondary_unique_key'])
        ->first();

        if (!empty($record)) {

        }
    }
}
