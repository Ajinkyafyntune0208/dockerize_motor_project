<?php

namespace App\Jobs;

use App\Exports\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use App\Models\FastlaneRequestResponse;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RcReportSingleJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $offset, $end , $data, $excelFileName;
    public function __construct($offset ,$end, $data, $excelFileName)
    {
        $this->offset = $offset;
        $this->end = $end;
        $this->excelFileName = $excelFileName;
        $this->data = $data;   
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //handle memory size from exhausting
        ini_set('memory_limit', '2048M');
        // handle execution time
        set_time_limit(0);
        $data = FastlaneRequestResponse::RcReportData($this->data)->orderby('id', 'DESC')->offset($this->offset)->limit($this->end)->get();

        $headings = [
            'Transaction Type',
            'Journey Type',
            'Rc Number',
            'Response',
            'Endpoint Url',
            'Response Time',
            'Created At',
        ];
        $records[] = $headings;

        foreach ($data as $record) {
            $rowData = [
                $record['transaction_type'],
                $record['type'],
                $record['request'],
                $record['response'],
                $record['endpoint_url'],
                Carbon::parse($record['response_time'])->format('s'),
                $record['created_at'],
            ];
            $records[] = $rowData;
        }             

        Excel::store(new DataExport($records), $this->excelFileName);
    }
}
