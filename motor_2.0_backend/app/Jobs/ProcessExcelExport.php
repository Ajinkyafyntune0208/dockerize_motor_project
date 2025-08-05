<?php

namespace App\Jobs;

use App\Exports\VahanUploadExport;
use App\Mail\VahanExcelReady;
use App\Models\VahanImportExcelLogs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ProcessExcelExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 7200;
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
        set_time_limit(0); 
        //
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1800);

   try {

        $Exceldata = VahanImportExcelLogs::where('status', '0')->first();
        if (empty($Exceldata)) return;

        $fileName = $Exceldata->unique_id .  '.xlsx';

        $fullPath = 'vahan_excel_import/' . $fileName;

        if (!Storage::exists($fullPath)) {
            Excel::store(new VahanUploadExport($Exceldata->start_date, $Exceldata->end_date), 'vahan_excel_import/' . $fileName);
        }
        
        $email = $Exceldata->user_email;
        $expiryMinutes = config('app.password_reset.expiry_minutes', 60);
        $validity = Carbon::now()->addMinutes($expiryMinutes)->timestamp;

        $token = $email . '|' . $validity . '|' . $Exceldata->unique_id;
        $token = base64_encode($token);

        $url = ENV('APP_URL') . ('/admin/download-vahan-excel') . '?' . http_build_query(['token' => $token]);

            Mail::to($Exceldata->user_email)->send(new VahanExcelReady($url, $expiryMinutes));
            $Exceldata->update(['status' => '1', 'file_path' => $fullPath]);

        } catch (\Exception $e) {
            
            throw new \Exception($Exceldata->unique_id . ' ' . $e->getMessage());
        }
    }
}