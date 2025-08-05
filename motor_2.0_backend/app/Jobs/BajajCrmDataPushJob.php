<?php

namespace App\Jobs;

use App\Models\BajajCrmData;
use Illuminate\Bus\Queueable;
use App\Models\UserProductJourney;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class BajajCrmDataPushJob implements ShouldQueue
{
    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */

    public $timeout =  300;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */

    /*
    Enum Status
    0 => Unprocessd,
    1 => Proccessing
    2 => Success
    3 => Failed
    */

    public function handle()
    {
        if (config('bajaj_crm_data_push') === "N") {
            return;
        }

        /* Process Unprocess Request */
        self::processLead("0");
        /* Process Failed Request */
        self::processLead("3");
    }

    public function processLead($status = 0)
    {
        $data = BajajCrmData::where("status", $status)->where('attempt', '<', '4')->get();

        if (!empty($data)) {

            foreach ($data as $key => $value) {

                try {

                    /* Update Attempt */
                    BajajCrmData::where("id", $value->id)->update(['status' => '1', "attempt" => ($value->attempt + 1)]);

                    $lead_id = UserProductJourney::find($value->user_product_journey_id);

                    /* Check CRM ID Present or Not If Present Pass It or Pass NULL */
                    $crmId = ['marketingId' => ($lead_id->lead_id ?? NULL)];
                    $requestData = array_merge($crmId, $value->payload);

                    $token = cache()->remember('bajaj_crm_token_generation_service', 60 * 25, function () {
                        return httpRequest('bajaj_crm_token_generation')['response']['data']['access_token'];
                    });

                    $response = httpRequest('bajaj_crm_data_update', $requestData, [], [
                        'Authorization' => 'Bearer ' . $token
                    ]);

                    /* Update Lead Id */
                    UserProductJourney::where('user_product_journey_id', $value->user_product_journey_id)
                        ->update(['lead_id' => ($response['response']['data']['lead_id'] ?? null)]);

                    /* Update Status as Success */
                    BajajCrmData::where("id", $value->id)->update(['status' => '2']);

                } catch (\Exception $e) {
                    /* Update Status as Failed */
                    BajajCrmData::where("id", $value->id)->update(['status' => '3']);
                    continue;
                }
            }
        }
    }
}
