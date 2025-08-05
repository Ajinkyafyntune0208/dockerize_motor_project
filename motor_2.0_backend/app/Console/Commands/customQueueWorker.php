<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class customQueueWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'custom:queue {dbname} {--sleep=3} {--tries=3} {--max-time=3600}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For single repo and multiple DB connection this queue command should be used.';

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

        $optionString = '';
        $optionString .= '--sleep=' . $this->option('sleep');
        $optionString .= ' --tries=' . $this->option('tries');
        $optionString .= ' --max-time=' . $this->option('max-time');
        $this->info('Disconnecting current DB connection.');
        \Illuminate\Support\Facades\DB::disconnect();
        $this->info('Setting ' . $this->argument('dbname') . ' as default DB connection.');
        \Illuminate\Support\Facades\Config::set('database.default', $this->argument('dbname'));
        try {
            $this->info('Re-Connecting new DB connection.');
            \Illuminate\Support\Facades\DB::reconnect();
        } catch(\Exception $e) {
            $this->error('Error occured while connecting the mentioned Database : ' . $this->argument("dbname"));
            $this->error($e->getMessage());
            return 0;
        }
        $this->info('Running artisan queue:work with options as : ' . $optionString);
        \Illuminate\Support\Facades\Artisan::call('queue:work ' . $optionString);
        throw new \RuntimeException('Custom Queue worker stopped listening.');
    }
}
