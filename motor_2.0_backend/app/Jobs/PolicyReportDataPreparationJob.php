<?php

namespace App\Jobs;

use App\Models\PolicyReportData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\PolicyReportJob;
class PolicyReportDataPreparationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = PolicyReportData::select('id', 'request', 'user_details')->where('is_dispatched', 'N')->get()->toArray();

        foreach ($data as $key => $value) {
            $requestData = new Request();
            $requestData->replace(array_merge($value['request'], [
                "return_only_count" => true,
                'skip_secret_token'=> true
            ]));
            request()->replace($requestData->input());
            $data = json_decode(\App\Http\Controllers\ProposalReportController::proposalReports($requestData)->getContent(), true);
            $excelDataCount = $data['excelDataCount'];
            PolicyReportJob::dispatch($value['request'], $excelDataCount, $value['user_details']);
            PolicyReportData::where('id', $value['id'])->update([
                'is_dispatched' => 'Y',
            ]);
        }

        Log::info("Policy report data prepared successfully.");
    }
}
