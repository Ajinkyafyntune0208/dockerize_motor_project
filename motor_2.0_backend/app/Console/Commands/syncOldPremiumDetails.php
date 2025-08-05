<?php

namespace App\Console\Commands;

use App\Http\Controllers\KafkaController;
use App\Http\Controllers\PremiumDetailController;
use App\Models\CvJourneyStages;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class syncOldPremiumDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oldPremiumDetails {start_date} {end_date} {--enquiry_id=} {--company_alias=all} {--stages=all} {--product_sub_types=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync old premium details based on the webservice logs';

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
        $start_date = $this->argument('start_date');
        $end_date = $this->argument('end_date');
        $start_date_is_valid = date('Y-m-d', strtotime($start_date)) == date($start_date);
        $end_date_is_valid = date('Y-m-d', strtotime($end_date)) == date($end_date);

        if (!$start_date_is_valid || !$end_date_is_valid) {
            $this->error('Start date or End date is not valid. It should be in Y-m-d format');
            return 0;
        }

        $all_trace_ids = CvJourneyStages::select('j.user_product_journey_id', 'j.product_sub_type_id', 'ql.master_policy_id', 'mc.company_alias', 'cv_journey_stages.stage')
            ->join('user_product_journey as j', 'j.user_product_journey_id', 'cv_journey_stages.user_product_journey_id')
            ->join('quote_log as ql', 'ql.user_product_journey_id', 'j.user_product_journey_id')
            ->join('master_company as mc', 'mc.company_id', '=', 'ql.ic_id')
            ->whereBetween('cv_journey_stages.updated_at', [Carbon::parse($start_date)->startOfDay(), Carbon::parse($end_date)->endOfDay()])
            ->whereNotIn('stage', [ STAGE_NAMES['LEAD_GENERATION'], STAGE_NAMES['QUOTE'], STAGE_NAMES['PROPOSAL_DRAFTED']])
            ->when($all_options['stages'] != 'all', function ($q) use ($all_options) {
                return $q->whereIn('stage', array_filter(explode('~', $all_options['stages'])));
            })->when($all_options['product_sub_types'] != 'all', function ($q) use ($all_options) {
            return $q->whereIn('j.product_sub_type_id', array_filter(explode(',', $all_options['product_sub_types'])));
        })->when(!empty($all_options['enquiry_id']), function ($q) use ($all_options) {
            $ids = collect(array_filter(explode(',', $all_options['enquiry_id'])))
                ->map(function ($value) {
                    return (int) $value;
                });
            return $q->whereIn('j.user_product_journey_id', $ids);
        })->when($all_options['company_alias'] != 'all', function ($q) use ($all_options) {
            return $q->whereIn('mc.company_alias', array_filter(explode(',', $all_options['company_alias'])));
        })->whereNotNull('j.product_sub_type_id')->get();

        if ($all_trace_ids->isEmpty()) {
            $this->info('No records found for the specified date range. From ' . $start_date . ' till ' . $end_date);
            return 0;
        }

        $premiumDetailController = new PremiumDetailController();

        if ($this->confirm($all_trace_ids->count() . ' Record(s) found. Do you wish to continue ?')) {
            $progressbar = $this->output->createProgressBar($all_trace_ids->count());
            $syncedData = [];
            $progressbar->start();
            $all_trace_ids->each(function ($trace_id, $key) use ($progressbar, $premiumDetailController, &$syncedData) {
                $progressbar->advance();
                $data = $premiumDetailController->syncOldPremiumDetails($trace_id->user_product_journey_id, $trace_id->product_sub_type_id, $trace_id->company_alias);
                $data['enquiry_id'] = $trace_id->user_product_journey_id;
                $data['product_sub_type_id'] = $trace_id->product_sub_type_id;
                $data['master_policy_id'] = $trace_id->master_policy_id;
                $data['company_alias'] = $trace_id->company_alias;
                $data['stage'] = $trace_id->stage;
                $syncedData[] = $data;
            });
            $progressbar->finish();
            $syncedData = collect($syncedData);
            $successCases = $syncedData->where('status', true);
            $failedCases = $syncedData->where('status', false);
            $this->info($this->newLine() . 'Success Case(s) : ' . $successCases->count());
            $this->info($this->newLine() . 'Failed Case(s) : ' . $failedCases->count());
            if ($successCases->count() > 0 && $this->confirm($this->newLine() . 'Do you want to perform the data push of the ' . $successCases->count() . 'success cases ?')) {
                $dataPushController = new KafkaController();
                $progressbar2 = $this->output->createProgressBar($successCases->count());
                $successCases->each(function ($trace_id, $key) use ($progressbar2, $dataPushController) {
                    $progressbar2->advance();
                    $dataPushController->ManualDataPush(new Request([
                        'enquiryId' => $trace_id['enquiry_id'],
                    ]), $trace_id['enquiry_id'], false);
                });
                $this->info($this->newLine() . 'Jobs successfully initiated for these cases.');
                $progressbar->finish();
            }
            if ($failedCases->count() > 0 && $this->confirm('Do you want to print the failure cases ?')) {
                $this->table(array_keys($failedCases->first()), $failedCases->toArray());
            }
        } else {
            $this->info('Sync process not initiated.');
        }
        return 0;
    }
}
