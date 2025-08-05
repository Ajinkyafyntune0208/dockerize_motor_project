<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CvAgentMapping;
use App\Models\UserProductJourney;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ModifySellerTypeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $offset;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Int $offset)
    {
        $this->offset = $offset;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $limit = 500;
        $user_product_journey_ids = UserProductJourney::with([
            'agent_details' => function ($query) {
                return $query->select(['id', 'user_product_journey_id', 'seller_type', 'agent_id', 'user_id'])
                ->where('agent_id', 'not like', '%-%');
            }
        ])
            ->whereHas('agent_details')
            ->select('user_product_journey_id')->offset($this->offset)->limit($limit)->get();
        $user_id_delete = $data = [];
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($user_product_journey_ids as $key => $user_product_journey_id) {
                // for b2c changes
                if ($user_product_journey_id->agent_details->where("seller_type", "U")->count() == 1 && $user_product_journey_id->agent_details->count() == 1) {
                    $user_product_journey_id->agent_details[0]->update([
                        "seller_type" => null,
                        "agent_id" => null,
                        "user_id" => $user_product_journey_id->agent_details[0]->agent_id
                    ]);
                    $data['b2c'][] = $user_product_journey_id;
                }
                // for b2c changes

                // for E AND P changes
                if ($user_product_journey_id->agent_details->whereIn("seller_type", ["E", "P", "Partner"])->count() == 1 && $user_product_journey_id->agent_details->count() == 2) {

                    $user_data = $user_product_journey_id->agent_details->where("seller_type", "U")->first() ?? $user_product_journey_id->agent_details->whereNull("seller_type")->first();
                    $seller_data = $user_product_journey_id->agent_details->whereIn("seller_type", ["E", "P", "Partner"])->first();
                    $seller_data->update([
                        "user_id" => $user_data->agent_id ?? $user_data->user_id
                    ]);

                    $user_id_delete[] = $user_data->id;
                    $data['E_P'][] = $user_product_journey_id;
                }
                if ($user_product_journey_id->agent_details->where("seller_type", "U")->count() == 2 && $user_product_journey_id->agent_details->count() == 2) {
                    $data['U_U'][] = $user_product_journey_id;
                }
                // for E AND P changes
            }
            CvAgentMapping::whereIn('id', $user_id_delete)->delete();
            $sql_data = [];
            foreach (\Illuminate\Support\Facades\DB::getQueryLog() as $key => $sql) {
                $addSlashes = str_replace('?', "'?'", $sql['query']);
                $sql_data[] = [
                    'query' => vsprintf(str_replace('?', '%s', $addSlashes), $sql['bindings'] ?? []),
                    'time' => $sql['time'] . ' ms'
                ];
            }
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $th) {
            \Illuminate\Support\Facades\DB::rollBack();
        }

        error_log(now() . ': ' . $this->offset +  $limit . " - " . json_encode($sql_data, JSON_PRETTY_PRINT) . "\n", 3, base_path() . '/storage/logs/ModifySellerTypeJob' . date('Y-m-d') . '.log');
        if ($user_product_journey_ids->count() > 0) {
            ModifySellerTypeJob::dispatch($this->offset +  $limit)/* ->delay(now()->addSeconds(2)) */;
        }
    }
}
