<?php

namespace App\Console\Commands;

use App\Models\CvJourneyStages;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PushOldDashboardData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:pusholddata {start_date} {end_date} {--kafka=false} {--product_sub_types=all} {--stages=all} {--ask_confirmation=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push data into MongoDB on the basis of user journey start and end date.';

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
        $all_options = $this->option();
        $is_kafka = $all_options['kafka'] === 'true';
        if (config('constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED') != 'Y' && !$is_kafka) {
            $this->error('Mongo Dashboard DataPush is not enabled.');
            return 0;
        }
        if (config('constants.motorConstant.KAFKA_DATA_PUSH_ENABLED') != 'Y' && $is_kafka) {
            $this->error('Kafka DataPush is not enabled.');
            return 0;
        }
        $start_date = $this->argument('start_date');
        $end_date = $this->argument('end_date');
        $start_date_is_valid = date('Y-m-d', strtotime($start_date)) == date($start_date);
        $end_date_is_valid = date('Y-m-d', strtotime($end_date)) == date($end_date);

        if (!$start_date_is_valid || !$end_date_is_valid) {
            $this->error('Start date or End date is not valid. It should be in Y-m-d format');
            return 0;
        }

        $all_trace_ids = CvJourneyStages::select('j.user_product_journey_id', 'j.product_sub_type_id')
            ->join('user_product_journey as j', 'j.user_product_journey_id', 'cv_journey_stages.user_product_journey_id')
            ->whereBetween('updated_at', [
                Carbon::parse($start_date)->startOfDay(),
                Carbon::parse($end_date)->endOfDay(),
            ])->when($all_options['stages'] != 'all', function ($q) use ($all_options) {
            $q->whereIn('stage', explode('~', $all_options['stages']));
        })->when($all_options['product_sub_types'] != 'all', function ($q) use ($all_options) {
            $q->whereIn('j.product_sub_type_id', explode(',', $all_options['product_sub_types']));
        })->get();

        if ($all_trace_ids->isEmpty()) {
            $this->info('No records found for the specified date range. From ' . $start_date . ' till ' . $end_date);
            return 0;
        }

        $jobs_details = [
            "kafka-motor" => [
                "JOBNAME" => "App\Jobs\KafkaDataPushJob",
                "QUEUE_NAME" => env('QUEUE_NAME'),
            ],
            "kafka-cv" => [
                "JOBNAME" => "App\Jobs\KafkaCvDataPushJob",
                "QUEUE_NAME" => env('QUEUE_NAME'),
            ],
            "dashboard" => [
                "JOBNAME" => "App\Jobs\DashboardDataPush",
                "QUEUE_NAME" => env('DASHBOARD_REPUSH_QUEUE_NAME', env('DASHBOARD_PUSH_QUEUE_NAME')),
            ],
        ];
        if (app()->runningInConsole()) {
            if ($all_options['ask_confirmation'] === 'false' || $this->confirm($all_trace_ids->count() . ' Record found. Do you wish to continue ?')) {
                $progressbar = $this->output->createProgressBar($all_trace_ids->count());
                $progressbar->start();
                $all_trace_ids->each(function ($trace_id, $key) use ($progressbar, $is_kafka, $jobs_details) {
                    $progressbar->advance();
                    if ($is_kafka) {
                        if (in_array($trace_id->product_sub_type_id, [1, 2])) {
                            $job_detail = $jobs_details['kafka-motor'];
                            $job_detail['JOBNAME']::dispatch($trace_id->user_product_journey_id, 'policy', 'manual')->onQueue($job_detail['QUEUE_NAME']);
                        } else {
                            $job_detail = $jobs_details['kafka-cv'];
                            $job_detail['JOBNAME']::dispatch($trace_id->user_product_journey_id, 'policy', 'manual')->onQueue($job_detail['QUEUE_NAME']);
                          }
                    } else {
                        $job_detail = $jobs_details['dashboard'];
                        $job_detail['JOBNAME']::dispatch($trace_id->user_product_journey_id)->onQueue($job_detail['QUEUE_NAME']);
                    }
                });
                $progressbar->finish();
                $this->info($this->newLine() . 'Jobs successfully initiated for these cases.');
            } else {
                $this->info('Data push process not initiated.');
            }
        } else {
            $this->info($all_trace_ids->count() . ' Records found.');
            $this->info('Further processing can be done by running the same command on server.');
        }
        return 0;
    }
}
