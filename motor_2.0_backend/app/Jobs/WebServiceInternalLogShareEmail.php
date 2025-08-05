<?php

namespace App\Jobs;

use App\Mail\WebServiceShare;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\WebServiceRequestResponse;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class WebServiceInternalLogShareEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

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
        if (config('constants.motorConstant.WEB_SERVICE_LOG_SHARE') != 'Y') {
            return;
        }
        set_time_limit(0);
        $logs = \App\Models\WebServiceRequestResponse::with('vehicle_details')->whereBetween('created_at', [
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay(),
        ])->where('endpoint_url', 'Internal Service')->get();
        $file_name = 'Webservice-Log/Webservice-Log' . now()->format('Y-m-d-H-i-s') . '.xls';
        \Maatwebsite\Excel\Facades\Excel::store(new \App\Exports\WebServiceExport($logs), $file_name);
        $emails = explode(',', config('constants.motorConstant.WEB_SERVICE_LOG_SHARE_EMAIL'));
        $data = [
            'file_name' => file_url($file_name),
        ];
        \Illuminate\Support\Facades\Mail::to($emails)->send(new WebServiceShare($data));
    }
}
