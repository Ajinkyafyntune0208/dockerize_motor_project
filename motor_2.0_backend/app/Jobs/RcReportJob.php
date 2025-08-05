<?php

namespace App\Jobs;

use ZipArchive;
use Illuminate\Bus\Batch;
use App\Mail\RcReportMail;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Models\VahanExportLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use App\Models\FastlaneRequestResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RcReportJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $data, $user_details;

    public function __construct($data, $user_details)
    {
        $this->data = $data;    
        $this->user_details = $user_details;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = FastlaneRequestResponse::RcReportData($this->data);
        
        $count = $data->count();
        $offset =0;
        $end = 100000;
        $i = 1 ;
        $offset_value[] = $offset;
        $request = $this->data;
        $user_details = $this->user_details;
     
       for($offset; $i < ceil($count / $end); $i++){
           $offset = $offset + $end;
           $offset_value[] = $offset;
        };
       
        $index = 1;
        $folder = getUUID();
        foreach($offset_value as $value){
            $excelFileName =$folder.'/file-'. $index . '-' .time().'.xls';
            $record_chain[] =  new \App\Jobs\RcReportSingleJob($value, $end, $this->data, $excelFileName);
            $index++;
            $files[]  = $excelFileName;
        }

        $batch = Bus::batch([$record_chain])->then(function (batch $batch) use ($files, $folder, $request, $user_details){
            $zip = new ZipArchive();
            $zipFileName = 'VahanExport-' . time() . '.zip';
            if ($zip->open(Storage_path($zipFileName), ZipArchive::CREATE)) {
                foreach ($files as $file) {
                    // $zip->addFile(Storage::path($file), basename($file));
                    $fileContent = Storage::disk(config('filesystems.default'))->get($file);
                    $zip->addFromString(basename($file), $fileContent);
                }
                $zip->close();
            }
  
            $uid = getUUID();
            $url = route('admin.RcReportDownload',['uid' => $uid]);
          
            Storage::deleteDirectory($folder);
            
            VahanExportLog::create([
                'uid'   => $uid,
                'user_id' => $user_details['id'],
                'request' => json_encode($request),
                'file' => $zipFileName,
                'source' => 'job',
                'file_expiry' => Carbon::now()->addDays(config('vahanExport.fileExpiry.days'))->format('Y-m-d H:i:s'),
            ]);
            Mail::to($user_details['email'])->send(new RcReportMail($url));
            Storage::disk(config('filesystems.default'))->put($zipFileName, file_get_contents(storage_path($zipFileName)));
            unlink(storage_path($zipFileName));
        })->finally(function (Batch $batch) {
            $msg = 'Batch [' . $batch->id . ']/[' . $batch->name . '] finished proccessing';
            info($msg);
        })->name('Export-Excel')->dispatch();
    }
}