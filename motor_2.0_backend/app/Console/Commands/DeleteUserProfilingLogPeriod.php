<?php

namespace App\Console\Commands;

use App\Models\UserProfilingModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DeleteUserProfilingLogPeriod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:delete_user_profiling_log_period';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It deletes the previous 10 days data from user_profiling table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        UserProfilingModel::where('created_at', '<', Carbon::now()->subDay(config('DELETE_USER_PROFILEING_LOG_PERIOD', 7)))->delete();
    }
}
