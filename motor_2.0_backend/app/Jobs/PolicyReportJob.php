<?php

namespace App\Jobs;

use App\Exports\DataExport;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PolicyReportJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected  $request, $excelDataCount, $user_details;
    public function __construct($request, $excelDataCount, $user_details)
    {
        $this->request = $request;
        $this->excelDataCount = $excelDataCount;
        $this->user_details = $user_details;
    }

    /**
     * Execute the job.
     *
     * @return void
     */

    public function handle()
    {
        $count = $this->excelDataCount;
        $limit = config('EXCEL_PER_PAGE_LIMIT', 100);
        $page = 1;
        $max = ceil($count / $limit);
        $record_chain = [];
        $files = [];

        $request = $this->request;
        $user_details = $this->user_details;
        $folder = getUUID();

        do {
            $excelFileName = $folder . '/file-' . $page . '-' . time() . '.xls';

            $record_chain[] = new \App\Jobs\PolicyReportSinglejob($page, $limit, $this->request, $excelFileName);

            $files[] = $excelFileName;

            $page++;
        } while ($page <= $max);


        Bus::batch($record_chain)->then(function (Batch $batch) use ($files, $folder, $request, $user_details) {
            $headings = [
                'Payment Date',
                'Proposal Date',
                'Enquiry Id',
                'Vehicle Registration No',
                'Policy No',
                'Business Type',
                'Section',
                'Premium Amount'
            ];

            $mergedData = [$headings];
            foreach ($files as $file) {
                $data = Excel::toArray([], $file);
                $mergedData = array_merge($mergedData, $data[0] ?? []);
            }
            
            Storage::deleteDirectory($folder);
            $mergedExcelFile = 'PolicyReport/'.'PolicyReportExcel-' . $request['from'] . "-" . $request['to'] . time() . '.xls';
            Excel::store(new DataExport($mergedData), $mergedExcelFile);


            if (config('filesystems.default') == 's3') {
                $filePath = file_url($mergedExcelFile);
            } else {
                $filePath = Storage::path($mergedExcelFile);
            }

            $emailTo = [$user_details['email']];
            $subject = "Policy Report"; 
            $reportSummary = "Your Policy Report is Ready!";
            foreach ($request as $key => $value) {
                if (is_array($value)) {
                    $reportSummary .= "\n" . $key . ' : ';
                    foreach ($value as $v) {
                        $reportSummary .= $v . ",";
                    }
                } else {
                    $reportSummary .= "\n" . $key . ' : ' . $value;
                }
            }

            Mail::send([], [], function ($message) use ($filePath, $emailTo, $subject, $reportSummary) {
                $message->to($emailTo)
                    ->subject($subject)
                    ->attach($filePath, [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->setBody($reportSummary);
            });
            Storage::delete($filePath);
            Log::info('Batch [' . $batch->id . ']/[' . $batch->name . '] finished processing');
        })->catch(function (Batch $batch, Throwable $e) use ($folder) {
            Storage::deleteDirectory($folder);
            Log::error('policy Report batch process failed. Batch Id : ' . $batch->id);
            Log::error($e);
        })->name('Export-Policy-Report')->dispatch();
    }
}
