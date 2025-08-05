<?php

namespace App\Console\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use App\Models\UserProductJourney;
use App\Jobs\AbiblRenewalJobWhatsapp;

class AbiblSendRenewalWhatsappNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AbiblSendRenewalWhatsappNotification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $anyCampgain = [ 'ABIBL_MG_DATA', 'HYUNDAI','',NULL ];
        $renewal_days = config('RENEWAL_NOTIFICATION_DAYS');
        $renewal_days = explode(',', $renewal_days);
        foreach ($renewal_days as $key => $days) {
            $user_product_journeys = UserProductJourney::whereIn('lead_source', $anyCampgain )->with(['user_proposal' => function ($query) {
                $query->select(['user_proposal_id', 'user_product_journey_id', 'policy_end_date', 'vehicale_registration_number', 'first_name', 'last_name', 'mobile_number']);
            }])->whereHas('user_proposal', function ($query) use ($days) {
                $query->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') = CURDATE() + INTERVAL {$days} DAY");
            })->whereHas('journey_stage', function ($query) {
                $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
            })->whereHas('corporate_vehicles_quote_request', function ($query) {
                $query->whereNotNull('version_id')->whereRaw('version_id != ""');
            })->whereNotIn('user_product_journey_id', function ($query) use ($days) {
                $query->select('old_user_product_journey_id')
                    ->from('communication_logs')
                    ->whereDate('created_at', Carbon::now())
                    ->where('communication_module', 'RENEWAL')
                    ->where('days', $days);
            })->whereNotIn('user_product_journey_id', function ($query) use ($days) {
                $query->select('old_user_product_journey_id')
                    ->from('communication_logs_1')
                    ->whereDate('created_at', Carbon::now())
                    ->where('communication_module', 'RENEWAL')
                    ->where('days', $days);
            })->whereNotIn('user_product_journey_id', function ($query) use ($days) {
                $query->select('old_user_product_journey_id')
                    ->from('communication_logs_2')
                    ->whereDate('created_at', Carbon::now())
                    ->where('communication_module', 'RENEWAL')
                    ->where('days', $days);
            })->whereNotIn('user_product_journey_id', function ($query) use ($days) {
                $query->select('old_user_product_journey_id')
                    ->from('communication_logs_3')
                    ->whereDate('created_at', Carbon::now())
                    ->where('communication_module', 'RENEWAL')
                    ->where('days', $days);
            })->whereNotIn('user_product_journey_id', function ($query) use ($days) {
                $query->select('old_user_product_journey_id')
                    ->from('communication_logs_4')
                    ->whereDate('created_at', Carbon::now())
                    ->where('communication_module', 'RENEWAL')
                    ->where('days', $days);
            })->get(['user_product_journey_id', 'created_on'])->split(4);

            foreach ($user_product_journeys as $key => $value) {
                $queue_value = $key + 1;
                foreach ($value as $data_key => $data_value) {
                    $trace_id = $data_value->user_proposal->user_product_journey_id;
                    AbiblRenewalJobWhatsapp::dispatch($trace_id, $queue_value)->onQueue(env('ABIBIL_RENEWAL_WHATSAPP_QUEUE') . $queue_value);
                }
            }
        }
        return 0;
    }
}
