<?php

namespace App\Jobs;

use App\Models\OfflineMigrationLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DataUploadMigrationV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $fileName;
    public function __construct($fileName = null)
    {
        $this->fileName = $fileName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->fileName)) {
            $files = Storage::allFiles('renewalDataUploadV2');
            foreach ($files as $value) {
                self::dispatch($value)
                ->onQueue(config('RENEWAL_MIGRATION_QUEUE'));
            }
        } else {
            self::process($this->fileName);
        }
    }

    public static function process($fileName)
    {
        $fileContent = json_decode(Storage::get($fileName), true);
        Storage::delete($fileName);

        foreach ($fileContent['data']['policies'] as $value) {
            $log = OfflineMigrationLog::create([
                'reference_number' => $fileContent['meta']['reference'],
                'unique_key' => $value['secondary_unique_key'],
                'policy_number' => $value['policy_number'] ?? null,
                'data' => $value,
                'uploaded_at' => $fileContent['meta']['uploaded_at']
            ]);

            $value['log_id'] = $log->id;

            self::migrate($value);
        }
    }

    public static function migrate($value)
    {
        $uniqueKey = $value['secondary_unique_key'];
        $logId = $value['log_id'];

        $proposalExtraFields = \App\Models\ProposalExtraFields::where('upload_secondary_key', $uniqueKey)->first();
        $userProductJourneyId = null;

        $productSubTypeId = \App\Models\MasterProductSubType::select("product_sub_type_id")
            ->where("product_sub_type_code", \Illuminate\Support\Str::upper($value['product_name']))
            ->pluck('product_sub_type_id')
            ->first();

        $masterCompany = null;

        if (!empty($value['company_alias'])) {
            $masterCompany = \App\Models\MasterCompany::where('company_alias', $value['company_alias'])
                ->first();
        }

        $transactionDate = !empty($value["policy_end_date"]) ? Carbon::parse($value["policy_end_date"])->subYear()->format('Y-m-d H:i:s') : now()->toDateTimeString();

        $policyType = 'comprehensive';

        if (!empty($value['policy_type'])) {
            $policyTypeList = [
                'OD' => 'own_damage',
                'COMPREHENSIVE' => 'comprehensive',
                'TP' => 'third_party'
            ];

            $policyType = $policyTypeList[$value['policy_type']] ?? 'comprehensive';
        }

        // Check if proposal extra fields exist for the unique key
        if (!empty($proposalExtraFields)) {
            $userProductJourneyId = $proposalExtraFields->enquiry_id;

            $userProductJourney = \App\Models\UserProductJourney::where('user_product_journey_id', $userProductJourneyId)
                ->first();

            $userProductJourneyId = $userProductJourney->user_product_journey_id;

            \App\Models\UserProductJourney::where('user_product_journey_id', $userProductJourneyId)
                ->update([
                    'product_sub_type_id' => $productSubTypeId,
                    'lead_source' => 'RENEWAL_DATA_UPLOAD',
                    'sub_source' => 'AR_REFERENCE',
                ]);
        } else {

            if (!empty($value['policy_number'])) {
                $policyDetails = \App\Models\PolicyDetails::where('policy_number', $value['policy_number'])
                    ->first();
                if (
                    !empty($policyDetails) &&
                    !empty($policyDetails?->user_proposal?->user_product_journey)
                ) {

                    // If policy number already exists, update the log and return
                    OfflineMigrationLog::where([
                        'id' => $logId
                    ])->update([
                        'status' => 'Failed',
                        'extras' => [
                            'Reason' => 'Policy number is already present.'
                        ]
                    ]);

                    return false;
                }
            }

            // Create a new user product journey
            $userProductJourney = \App\Models\UserProductJourney::create([
                'product_sub_type_id' => $productSubTypeId,
                'lead_source' => 'RENEWAL_DATA_UPLOAD',
                'sub_source' => 'AR_REFERENCE',
                'created_on' => $transactionDate
            ]);

            $userProductJourneyId = $userProductJourney->user_product_journey_id;

            \App\Models\ProposalExtraFields::updateOrCreate(["enquiry_id" => $userProductJourneyId], [
                "upload_secondary_key" => $uniqueKey
            ]);
        }

        OfflineMigrationLog::where([
            'id' => $logId
        ])->update([
            'user_product_journey_id' => $userProductJourneyId
        ]);

        $updateDates = true;

        if (
            !empty($userProductJourney?->user_proposal?->policy_details)
        ) {
            if ($userProductJourney->lead_source != 'RENEWAL_DATA_UPLOAD') {
                OfflineMigrationLog::where([
                    'id' => $logId
                ])->update([
                    'status' => 'Failed',
                    'extras' => [
                        'Reason' => 'Online policy already exists for this journey.'
                    ]
                ]);
                return;
            }

            if (empty($value['policy_end_date'])) {
                $updateDates = false;
            }
        }

        if (!empty($value['policy_number'])) {

            $quoteData = [
                'product_sub_type_id' => $productSubTypeId,
                'ic_alias' => !empty($masterCompany) ? $masterCompany->company_alias : null,
                'ic_id' => !empty($masterCompany) ? $masterCompany->company_id : null,
            ];

            if ($updateDates) {
                $quoteData['searched_at'] = $quoteData['created_at'] = $transactionDate;
            }

            $quoteData = array_filter($quoteData, function ($item) {
                return !empty($item);
            });

            \App\Models\QuoteLog::updateOrCreate([
                'user_product_journey_id' => $userProductJourneyId
            ], $quoteData);

            $rtoCode = $rcNumber = null;
            if (!empty($value['vehicle_registration_number'])) {
                if (strtoupper($value['vehicle_registration_number']) != "NEW") {
                    $rcNumberArray = str_split($value['vehicle_registration_number']);
                    if ($rcNumberArray[0] . $rcNumberArray[1] == 'DL') {
                        $vehicleRegHyphen = getRegisterNumberWithHyphen($value['vehicle_registration_number']);
                        $regArray = explode("-", $vehicleRegHyphen);
                        $regArray[1] = (strlen($regArray[1]) == 1) ? ($regArray[1] = "0" . $regArray[1]) : $regArray[1];
                        $rtoCode = $regArray[0] . "-" . $regArray[1];
                        $rcNumber = $regArray[0] . "-" . $regArray[1] . '-' . $regArray[2] . '-' . ($regArray[3] ?? '');
                    } else {
                        $vehicleRegHyphen = getRegisterNumberWithHyphen($value['vehicle_registration_number']);
                        $regArray = explode("-", $vehicleRegHyphen);
                        $rtoCode = $regArray[0] . "-" . $regArray[1];
                        $rcNumber = $vehicleRegHyphen;
                    }
                } else {
                    $rcNumber = 'NEW';
                    $rtoCode = null;
                }
            }

            $corporateData = [
                'business_type' => 'rollover',
                'policy_type' => $policyType,
                'created_on' => $transactionDate,
                'vehicle_registration_no' => $rcNumber,
                'rto_code' => $rtoCode,
                'product_id' => $productSubTypeId,
            ];

            if ($updateDates) {
                $corporateData['created_on'] = $transactionDate;
            }

            $corporateData = array_filter($corporateData, function ($item) {
                return !empty($item);
            });
            
            \App\Models\CorporateVehiclesQuotesRequest::updateOrCreate([
                'user_product_journey_id' => $userProductJourneyId
            ], $corporateData);

            if ($updateDates) {
                \App\Models\SelectedAddons::updateOrCreate([
                    'user_product_journey_id' => $userProductJourneyId
                ], [
                    'created_at' => $transactionDate,
                    'updated_at' => $transactionDate
                ]);
            } elseif (empty($userProductJourney?->addons)){
                \App\Models\SelectedAddons::Create([
                    'user_product_journey_id' => $userProductJourneyId
                ]);
            }

            if (!empty($value['policy_end_date'])) {
                $value['policy_end_date'] =date('d-m-Y', strtotime($value['policy_end_date']));
            }

            $proposalData = [
                'mobile_number' => $value['mobile_no'] ?? null,
                'ic_name' => !empty($masterCompany) ? $masterCompany->company_name : null,
                'ic_id' => !empty($masterCompany) ? $masterCompany->company_id : null,
                'insurance_company_name' => !empty($masterCompany) ? $masterCompany->company_name : null,
                'vehicale_registration_number' => $rcNumber,
                'policy_end_date' => $value['policy_end_date'] ?? null,
            ];

            if ($updateDates) {
                $proposalData['created_at'] = $transactionDate;
                $proposalData['proposal_date'] = $transactionDate;
            }

            $proposalData = array_filter($proposalData, function ($item) {
                return !empty($item);
            });
            \App\Models\UserProposal::updateOrCreate([
                'user_product_journey_id' => $userProductJourneyId
            ], $proposalData);

            $userProductJourney = \App\Models\UserProductJourney::where('user_product_journey_id', $userProductJourneyId)
                ->first();

            $paymentData = [
                'ic_id' => !empty($masterCompany) ? $masterCompany->company_id : null,
                'user_proposal_id' => $userProductJourney->user_proposal->user_proposal_id,
                'active' => 1,
                'created_at' => $transactionDate,
                'updated_at' => $transactionDate,
                'status' => STAGE_NAMES['PAYMENT_SUCCESS']
            ];
            
            if ($updateDates) {
                $paymentData['created_at'] = $transactionDate;
                $paymentData['updated_at'] = $transactionDate;
            }

            $paymentData = array_filter($paymentData, function ($item) {
                return !empty($item);
            });

            \App\Models\PaymentRequestResponse::updateOrCreate([
                'user_product_journey_id' => $userProductJourneyId
            ], $paymentData);

            $policyDetails = [
                'policy_number' => $value['policy_number'],
                'status' => 'SUCCESS',
                'rehit_source' => 'OFFLINE_DATA_UPLOAD',
                'created_on' => $transactionDate
            ];
            if ($updateDates) {
                $policyDetails['created_on'] = $transactionDate;
            }

            $policyDetails = array_filter($policyDetails, function ($item) {
                return !empty($item);
            });

            \App\Models\PolicyDetails::updateOrCreate([
                'proposal_id' => $userProductJourney->user_proposal->user_proposal_id
            ], $policyDetails);
        } else {

            \App\Models\CorporateVehiclesQuotesRequest::updateOrCreate([
                'user_product_journey_id' => $userProductJourneyId
            ], [
                'product_id' => $productSubTypeId,
            ]);
            if (!empty($value['mobile_no'])) {
                \App\Models\UserProposal::updateOrCreate([
                    'user_product_journey_id' => $userProductJourneyId
                ], [
                    'mobile_number' => $value['mobile_no'] ?? null,
                ]);
            }
        }

        if (!empty($value['mobile_no'])) {
            $userId = \App\Helpers\Broker\DataUploadHelper::generateUserId($value['mobile_no']);
            if (!empty($userId)) {
                \App\Models\CvAgentMapping::updateOrCreate([
                    'user_product_journey_id' => $userProductJourneyId
                ], [
                    "user_id" => $userId
                ]);
            }
        }

        if (!empty($value['seller_code']) && !empty($value['seller_type'])) {

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
                save:true
            )['response'];

            $response = $response[$sellerType][$value['seller_code']] ?? null;

            if ($response['status'] ?? null) {
                $agentResponse = $response['data'];

                $agentData = [
                    'seller_type' => $agentResponse['seller_type'] ?? null,
                    'agent_id' => $agentResponse['seller_id'] ?? null,
                    'user_name' => $value['seller_code'] ?? null,
                    'agent_name' => $agentResponse['seller_name'] ?? null,
                    'agent_mobile'  => $agentResponse['mobile'] ?? null,
                    'agent_email'   => $agentResponse['email'] ?? null,
                    'aadhar_no' => $agentResponse['seller_aadhar_no'] ?? null,
                    'pan_no' => $agentResponse['seller_pan_no'] ?? null,
                    'pos_key_account_manager' => ($agentResponse['seller_type'] ?? '') == "P" ? ($agentResponse["rm_id"] ?? null) : ($agentResponse['user_id'] ?? null),
                    'source' => "agent-update-api",
                ];

                \App\Models\CvAgentMapping::updateOrCreate([
                    'user_product_journey_id' => $userProductJourneyId,
                ], $agentData);
            }
        }

        $journeyData = [
            'proposal_id'  => $userProductJourney?->user_proposal?->user_proposal_id,
            'stage' => !empty($value['policy_number']) ? STAGE_NAMES['POLICY_ISSUED'] : STAGE_NAMES['LEAD_GENERATION'],
        ];

        if ($updateDates) {
            $journeyData['created_at'] = $transactionDate;
            $journeyData['updated_at'] = $transactionDate;
        }

        \App\Models\JourneyStage::updateOrCreate([
            'user_product_journey_id' => $userProductJourneyId,
        ], $journeyData);

        $journeyData = array_filter($journeyData, function ($item) {
            return !empty($item);
        });

        OfflineMigrationLog::where([
            'id' => $logId
        ])->update([
            'status' => 'Success',
        ]);

        \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
            'enquiryId' => $userProductJourneyId,
        ]), $userProductJourneyId, false);
    }
}
