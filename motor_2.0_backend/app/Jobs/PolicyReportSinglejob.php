<?php
namespace App\Jobs;

use App\Exports\DataExport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Batchable;  // Add this line
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PolicyReportSinglejob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable; 

    protected $page, $limit, $data, $excelFileName;
    
    public function __construct($page, $limit, $data, $excelFileName)
    {
        $this->page = $page;
        $this->limit = $limit;
        $this->data = $data;
        $this->excelFileName = $excelFileName;

    }

    public function handle()
    {
        // handle memory size from exhausting
        // ini_set('memory_limit', '2048M');
        // handle execution time
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 0);
        $requestData = new Request();
        $requestData->replace(array_merge($this->data, [
            "pagination" => true,
            "perPageRecords" => $this->limit,
            "page" => $this->page,
            'skip_secret_token'=> true
        ]));
        request()->replace($requestData->input());
        $data = json_decode(\App\Http\Controllers\ProposalReportController::proposalReports($requestData)->getContent(), true);
        $records = [];
        if(!empty($data['data'])){
            foreach ($data['data'] as $key => $value) {
                $rowData = [
                    $value['payment_time'],
                    $value['proposal_date'],
                    "'"  .$value['trace_id'],
                    $value['vehicle_registration_number'],
                    $value['policy_no'],
                    $value['business_type'],
                    $value['section'],
                    $value['premium_amount'],
                ];
            $records[] = $rowData;

            }
        }         
        Excel::store(new DataExport($records), $this->excelFileName);
    }
}