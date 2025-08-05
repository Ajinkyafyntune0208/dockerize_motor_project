<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CvAgentMapping;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class modifyUserIdsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Create a new job instance.
     *
     * @return void
     */

    protected $limit;
    public function __construct($limit)
    {
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::enableQueryLog();
       
        if (config('constants.motorConstant.IS_USER_ENABLED') != "Y") {
            return;
        }

        $data = DB::table('cv_agent_mappings')
        ->select('id','agent_id','seller_type','cv_agent_mappings.user_id as cv_user_id','user_mobile','user_fname','user_lname','user_email','user_product_journey.user_product_journey_id as journey_id','user_mobile')
        ->join('user_product_journey','cv_agent_mappings.user_product_journey_id','=','user_product_journey.user_product_journey_id')
        ->whereIn('cv_agent_mappings.seller_type',["E", "P", "Partner"])
        ->where(function ($query) { $query->whereNotNull('user_product_journey.user_mobile')
        ->orWhere('user_product_journey.user_mobile','!=','');})
        ->where(function ($query) { $query->whereNull('cv_agent_mappings.user_id')
        ->orWhere('cv_agent_mappings.user_id','=','');})
        ->limit($this->limit)
        ->get();


        if ($data->count() <= 0) {
            dd("No Data Found");
        }

        foreach ($data as $key => $value) {

            try {
                DB::beginTransaction();
                $name = trim(($value->user_fname ?? '') . " " . ($value->user_lname ?? ''));

                if (empty($value->cv_user_id) && !empty($value->user_mobile)) {
                    $response = httpRequestNormal(config('constants.motorConstant.BROKER_USER_CREATION_API'), 'POST', [
                        'mobile_no' => $value->user_mobile ?? NULL,
                        'email' => $value->email_address ?? NULL,
                        'first_name' => $name ?? NULL,
                    ], [
                        "Content-Type" => "application/x-www-form-urlencoded"
                    ], [], [], false, true, true);

                    if (isset($response['response']['status']) && $response['response']['status'] == "true") {
                        $user_id = $response['response']['user_id'] ?? NULL;
                        CvAgentMapping::where("user_product_journey_id", $value->journey_id)->update([
                            "user_id" => $user_id
                        ]);
                    }
                }
                DB::commit();
            } catch (\Exception $th) {
                DB::rollBack();
            }
        }

        $sql_data = [];

        foreach (\Illuminate\Support\Facades\DB::getQueryLog() as $key => $sql) {
            $addSlashes = str_replace('?', "'?'", $sql['query']);
            $sql_data[] = [
                'query' => vsprintf(str_replace('?', '%s', $addSlashes), $sql['bindings'] ?? []),
                'time' => $sql['time'] . ' ms'
            ];
        }

        error_log(now() . ': ' . " - " . json_encode($sql_data, JSON_PRETTY_PRINT) . "\n", 3, base_path() . '/storage/logs/ModifyUserIdsJob-' . date('Y-m-d') . '.log');
    }
}
