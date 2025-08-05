<?php

namespace App\Console\Commands;

use App\Traits\LogSummary;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\VisibilityReportLogSummary;

class SaveLogSummary extends Command
{
    use LogSummary;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save-log-summary {--current-month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will run hourly and cache summary request';

    protected $methods = [
        // Quote Services
        'quote_token',
        'quote_idv_service',
        'quote_premium_calculation',
        'quote_premium_re_calculation',
        // Proposal Services
        'proposal_token',
        'proposal_premium_calculation',
        'proposal_premium_re_calculation',
        'proposal_proposal_submit',
        // CKYC Services
        'ckyc_ckyc',
    ];

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
        if ($this->option('current-month')) {
            $from = Carbon::now()->startOfMonth();
            $to = Carbon::now();

            $timeRanges = generateHourlyDateRanges($from, $to);

            $timeRanges->each(function ($range) {
                $from = $range['from'];
                $to = $range['to'];
                $this->info("Summarising logs from " . $from->toDateTimeString() . " to " . $to->toDateTimeString());
                $this->generateSummaryBetween($from, $to);
            });
        } else {
            $from = Carbon::now()->subHour()->startOfHour();
            $to = Carbon::now()->subHour()->endOfHour();
            $this->info("Summarising logs from " . $from->toDateTimeString() . " to " . $to->toDateTimeString());
            $this->generateSummaryBetween($from, $to);
        }
    }

    public function generateSummaryBetween($from, $to)
    {
        foreach ($this->methods as $method) {
            DB::enableQueryLog();
            list($log_type, $method_name) = $this->getLogAndMethodName($method);

            if (VisibilityReportLogSummary::where('from', $from->getTimestamp())->where('to', $to->getTimestamp())->where('method_type', $method)->exists()) {
                $this->warn("Log Summary already saved for type " . $method);
                continue;
            }

            $this->initializeMetrics();
            $this->calculateMetrics($from->getTimestamp(), $to->getTimestamp(), $log_type, $method_name);

            VisibilityReportLogSummary::insert([
                'from' => $from->getTimestamp(),
                'to' => $to->getTimestamp(),
                'from_date' => $from->toDateTimeString(),
                'to_date' => $to->toDateTimeString(),
                'method_type' => $method,
                'data' => json_encode($this->getMetrics()),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            $this->info("Log summary saved for type " . $method);
        }
    }
}
