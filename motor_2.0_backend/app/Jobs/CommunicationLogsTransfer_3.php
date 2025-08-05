<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class CommunicationLogsTransfer_3 implements ShouldQueue
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
        DB::table('communication_logs_3')->whereDate('created_at', Carbon::today()->subDay(1))
        ->chunkbyId(100, function ($logs_data) 
        {
            foreach ($logs_data as $key => $value)
            {
                $value = json_decode(json_encode($value),TRUE);
                $last_id = $value['id'];
                unset($value['id']);
                if($value['prev_policy_end_end'] != NULL)
                {
                    $value['prev_policy_end_end'] = \Carbon\Carbon::parse($value['prev_policy_end_end'])->format('Y-m-d');
                }
                $inserted_data = DB::table('master_communication_logs')->insert($value);
                if($inserted_data)
                {
                    DB::table('communication_logs_3')->where('id',$last_id)->delete();
                }
            }
        });
        
    }
}
