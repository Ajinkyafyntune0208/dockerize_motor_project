<?php

namespace App\Jobs;

use App\Models\Agents;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Models\KotakPosMapping;

require_once app_path() . '/Helpers/CarWebServiceHelper.php';

class KotakPosRegistration implements ShouldQueue
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
        $all_pos_data = Agents::whereNotIn('agent_id', KotakPosMapping::pluck('agent_id'))
            ->where('usertype', 'P')
            ->where('status', 'Active')
            ->whereNotNull('pan_no')
            ->whereNotNull('licence_start_date')
            ->whereNotNull('licence_end_date')
            ->whereNotNull('agent_name')
            ->whereNotNull('user_name')
            ->where([
                 ['pan_no' , '!=', ''],
                 ['licence_start_date' , '!=', ''],
                 ['licence_end_date' , '!=', ''],
                 ['agent_name' , '!=', ''],
                 ['user_name' , '!=', ''],
            ])
            ->limit(50)
            ->get();
        $error = [];
        foreach ($all_pos_data as $key => $pos) {
            if (empty($pos->pan_no)) {
                $error[] = ['pos_data' => $pos];
                continue;
            }
            if (empty($pos->licence_start_date) || empty($pos->licence_end_date)) {
                $error[] = ['pos_data' => $pos];
                continue;
            }
            PosRegistrationProcessSingle::dispatch($pos, 'kotak');
        }
        info('Kotak Pos Registration', $error);
    }
}
