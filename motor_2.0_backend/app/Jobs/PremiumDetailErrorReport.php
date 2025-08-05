<?php

namespace App\Jobs;

use App\Http\Controllers\PremiumDetailController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\JourneyStage;
use App\Models\MasterCompany;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class PremiumDetailErrorReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $currentDateTime = now()->format('Y-m-d H:i:s');
        $from = $this->data['from'];
        $to = $this->data['to'];

        $stageList = [
            STAGE_NAMES['PROPOSAL_ACCEPTED'],
            STAGE_NAMES['PAYMENT_INITIATED'],
            STAGE_NAMES['PAYMENT_SUCCESS'],
            STAGE_NAMES['PAYMENT_FAILED'],
            STAGE_NAMES['POLICY_ISSUED'],
            STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
        ];

        if (!empty($this->data['stage'])) {
            $stageList = $this->data['stage'];
        }

        $broker = config('app.name');
        $result = [
            [
                'Trace Id',
                'Insurance Company',
                'Stage',
                'Message'
            ]
        ];

        $icList = MasterCompany::whereNotNull('company_alias')
        ->get()
        ->pluck('company_alias', 'company_id')
        ->toArray();

        JourneyStage::whereBetween('updated_at', [$from, $to])
        ->select('user_product_journey_id', 'stage', 'ic_id')
        ->whereIn('stage', $stageList)
        ->with(['user_product_journay'])
        ->chunk(500, function ($journeys) use (&$result, $icList) {
            foreach ($journeys as $journey) {
                if (
                    !empty($journey->user_product_journay) &&
                    $journey->user_product_journay->lead_source != 'RENEWAL_DATA_UPLOAD'
                ) {
                    $content = PremiumDetailController::verifyPremiumDetails($journey->user_product_journey_id);
                    if (!$content['status']) {
                        $result[] = [
                            $content['trace_id'] ?? null,
                            $icList[$journey->ic_id] ?? null,
                            $journey->stage,
                            $content['data']['stack_trace'] ?? $content['data']['message'] ?? null
                        ];
                    }
                }
            }
        });
        if(count($result) <= 1) {
            return;
        }   
        $directory = 'reports';

        $fileName = preg_replace('/[^A-Za-z0-9 ]/', '', $broker);

        $fileName = preg_replace('/\s+/', ' ', $fileName);
        $fileName = trim($fileName);
        $fileName = str_replace(' ', '-', $fileName);

        $fileName = str_replace(' ', '-', $fileName).'-premium-detail-error-report_' . now()->format('Ymd') . '.xls';
        $filePath = $directory . '/' . $fileName;

        Excel::store(new \App\Exports\DataExport($result), $filePath);

        $emailTo = config('PREMIUM_DETAILS_REPORT_RECIPIENTS', "");
        $emailTo = explode(',', $emailTo);

        $subject = 'Premium Detail Error Report for ' . $broker;

        $reportSummary = "Report Summary:\n\n";
        $reportSummary .= "Broker : $broker\n";
        $reportSummary .= "Date Range: $from to $to\n";
        $reportSummary .= "Generated on: $currentDateTime\n";

        $reportSummary .= "Attached: Premium Detail Error Report\n\n";
        $reportSummary .= "Please find the attached Excel report for detailed information.";
        
        if (config('filesystems.default') == 's3') {
            $filePath = file_url($filePath);
        } else {
            $filePath = Storage::path($filePath);
        }

        // Send the email
        Mail::send([], [], function ($message) use ($filePath, $emailTo, $subject, $reportSummary) {
            $message->to($emailTo)
            ->subject($subject)
            ->attach($filePath, [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
                ->setBody($reportSummary);
        });

        Storage::delete($filePath);
    }
}
