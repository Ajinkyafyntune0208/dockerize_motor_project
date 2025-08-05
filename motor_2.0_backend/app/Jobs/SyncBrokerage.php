<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class SyncBrokerage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $traceIdList;
    public $confId;
    public $checkConfId;
    public $extras;

    public function __construct($list = [], $checkConfId = true, $confId = null, $extras = [])
    {
        $this->traceIdList = $list;
        $this->checkConfId = $checkConfId;
        $this->confId = $confId;
        $this->extras = $extras;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (
            config('ENABLE_BROKERAGE_COMMISSION', 'N') != 'Y' ||
            empty(config('BROKER_ID_FOR_BROKERAGE_COMMISSION'))
        ) {
            return;
        }


        // If traceIdList is not empty, update the commission for each trace ID
        if (!empty($this->traceIdList)) {
            $this->processTraceIdsInBatches();
        } else {

            Log::info("Sync brokerage Job started");
            // If no traceIdList, fetch policies from the database
            self::fetchPolicy();
        }
    }

    /**
     * Process trace IDs in batches to improve performance.
     */
    public function processTraceIdsInBatches()
    {
        $jobs = [];

        foreach ($this->traceIdList as $traceId) {
            $jobs[] = new \App\Jobs\UpdateCommissionJob($traceId, $this->checkConfId, $this->confId, $this->extras);
        }

        Bus::batch($jobs)->dispatch();
    }

    public static function fetchPolicy()
    {
        $broker = config('BROKER_ID_FOR_BROKERAGE_COMMISSION');

        //write a query check retrospective changes on brocore commission config
        DB::connection('brocore')
        ->table('brokerage_rectro_spective_queue as brsq')
        ->select('brsq.rule_id')
        ->join('broker_configurator as bc', 'bc.conf_id', '=', 'brsq.rule_id')
        ->join(
            'lob_master as lm',
            'lm.lob_id',
            '=',
            'bc.lob_id'
        )
        ->where([
            'brsq.is_process' => 'N',
            'bc.broker_id' => $broker
        ])
        ->whereIn(
            'lm.lob_slug',
            \App\Models\MasterProductSubType::pluck('product_sub_type_code')->toArray()
        )
        ->orderBy('brsq.brokerage_rectro_spective_queue_id')
        ->chunk(5, function ($rows) {
            // Process each chunk of rows to retrieve rule IDs
            $confIdList = $rows->pluck('rule_id')->toArray();
            $confIdList = array_unique($confIdList);
            foreach ($confIdList as $value) {
                self::fetchAndDispatchPolicies($value);
            }
        });
    }


    public static function fetchAndDispatchPolicies($confId)
    {
        Log::info("Sync brokerage Process started for conf Id ". $confId);

        $commissionStructure = DB::connection('brocore')
        ->table('broker_configurator')
        ->select('commission_structure')
        ->where('conf_id', $confId)
        ->pluck('commission_structure')
        ->first();

        $extras = [];
        if ($commissionStructure == 'PAY_IN') {
            $extras['isPayIn'] = true;
        }

        $isPayIn = $extras['isPayIn'] ?? false;

        $batch = [];
        \App\Models\PremiumDetails::select('premium_details.user_product_journey_id')
        ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'premium_details.user_product_journey_id')
        ->join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'cjs.user_product_journey_id')
        ->where(function ($query) use ($confId, $isPayIn){
            if ($isPayIn) {
                $query->where('premium_details.payin_conf_id', $confId);
            } else {
                $query->where('premium_details.commission_conf_id', $confId);
            }
        })
        ->where(function ($query) {
            $query->where('upj.lead_source', '!=', 'RENEWAL_DATA_UPLOAD')
                ->orWhereNull('upj.lead_source');
        })
        ->whereIn('cjs.stage', [ STAGE_NAMES['POLICY_ISSUED']])
        ->chunk(100, function ($list) use (&$batch, $extras) {
            $traceIdList = $list->pluck('user_product_journey_id')->toArray();
            if (!empty($traceIdList)) {
                // Dispatch jobs to process these trace IDs
                $batch[] = new SyncBrokerage($traceIdList, true, null, $extras);
            }
        });

        if (!empty($batch)) {
            Bus::batch($batch)
            ->then(function (Batch $batch) use ($confId) {
                //Once the commission is updated for policies which has confID, update the same for those which doesnt have confId
                self::fetchOtherPolicy($confId);
                Log::info('Sync brokerage batch suucessfully processed (Logic No 1). Batch Id : ' . $batch->id);
            })
                ->catch(function (Batch $batch) {
                    Log::error('Sync brokerage batch process failed (Logic No 1). Batch Id : ' . $batch->id);
                    // This code runs if any job in the batch fails
                })
                ->dispatch();
        } else {
            self::fetchOtherPolicy($confId);
        }
    }

    public static function fetchOtherPolicy($confId)
    {
        // $commissionStructure = "PAY_OUT";
        $commissionType = 'BROKERAGE';

        $confDetail =  DB::connection('brocore')
        ->table('broker_configurator as bc')
        ->select('bc.effective_end_date', 'bc.effective_start_date', 'mc.company_slug', 'lm.lob_slug', 'bc.commission_structure')
        ->join(
            'lob_master as lm',
            'lm.lob_id',
            '=',
            'bc.lob_id'
        )
        ->join(
            'master_company as mc',
            'mc.company_id',
            '=',
            'bc.company_id'
        )
        ->where([
            'bc.broker_id' => config('BROKER_ID_FOR_BROKERAGE_COMMISSION'),
            'bc.conf_id' => $confId,
            // 'bc.commission_structure' => $commissionStructure,
            'bc.commission_type' => $commissionType,
            'bc.status' => 'Y'
        ])
        ->whereIn(
            'lm.lob_slug',
            \App\Models\MasterProductSubType::pluck('product_sub_type_code')->toArray()
        )
        ->first();
        

        if (!empty($confDetail)) {

            $isPayIn = $confDetail->commission_structure == 'PAY_IN';

            $extras = [];
            if ($isPayIn) {
                $extras['isPayIn'] = true;
            }

            $batchList = [];

            \App\Models\PremiumDetails::select('upj.user_product_journey_id')
            ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'premium_details.user_product_journey_id')
            ->join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'cjs.user_product_journey_id')
            ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'cjs.user_product_journey_id')
            ->join('master_company as mc', 'mc.company_id', '=', 'ql.ic_id')
            ->join('master_product_sub_type as mpst', 'mpst.product_sub_type_id', '=', 'upj.product_sub_type_id')
            ->join('master_product_sub_type as parent', 'mpst.parent_product_sub_type_id', '=', 'parent.product_sub_type_id')
            ->join('payment_request_response as prr', 'prr.user_product_journey_id', '=', 'upj.user_product_journey_id')
            ->where([
                ['parent.product_sub_type_code', '=', $confDetail->lob_slug],
                ['mc.company_alias', '=', $confDetail->company_slug],
                ['cjs.stage', '=', STAGE_NAMES['POLICY_ISSUED']],
                ['prr.active', '=', 1],
            ])
            ->where(function ($query) {
                $query->where('upj.lead_source', '!=', 'RENEWAL_DATA_UPLOAD')
                    ->orWhereNull('upj.lead_source');
            })
            ->where(function ($query) use ($confId, $isPayIn) {
                if ($isPayIn) {
                    $query->where('premium_details.payin_conf_id', '!=', $confId)
                    ->orWhereNull('premium_details.payin_conf_id');
                } else {
                    $query->where('premium_details.commission_conf_id', '!=', $confId)
                    ->orWhereNull('premium_details.commission_conf_id');
                }
            })
            ->whereBetween('prr.created_at', [$confDetail->effective_start_date, $confDetail->effective_end_date])
            ->chunk(100, function ($list) use (&$batchList, $confId, $extras) {
                $traceIdList = $list->pluck('user_product_journey_id')->toArray();
                if (!empty($traceIdList)) {
                    $batchList[] = new SyncBrokerage($traceIdList, false, $confId, $extras);
                }
            });

            if (!empty($batchList)) {
                Bus::batch($batchList)
                ->then(function (Batch $batch) use ($confId) {
                    DB::connection('brocore')
                        ->table('brokerage_rectro_spective_queue')
                        ->where('rule_id', $confId)
                        ->update(['is_process' => 'Y']);

                    Log::info('Sync brokerage batch suucessfully processed (Logic No 2). Batch Id : ' . $batch->id);
                })->catch(function (Batch $batch) {
                    Log::error('Sync brokerage batch process failed (Logic No 2). Batch Id : ' . $batch->id);
                    // This code runs if any job in the batch fails
                })
                ->dispatch();
            } else {
                DB::connection('brocore')
                ->table('brokerage_rectro_spective_queue')
                ->where('rule_id', $confId)
                ->update(['is_process' => 'Y']);
            }
        } else {
            DB::connection('brocore')
            ->table('brokerage_rectro_spective_queue')
            ->where('rule_id', $confId)
                ->update(['is_process' => 'Y']);
        }
    }
}
