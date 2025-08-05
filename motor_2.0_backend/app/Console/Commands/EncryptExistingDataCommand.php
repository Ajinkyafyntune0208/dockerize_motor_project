<?php

namespace App\Console\Commands;

use App\Jobs\EncryptExistingData;
use Illuminate\Console\Command;

class EncryptExistingDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt:existingData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command is used to encrypt the data that is there in DB';

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
        if ($this->confirm('Do you want to start the database encryption process ?')) {
            $queueName = config('constants.brokerConstant.ENCRYPTION_QUEUE_NAME');
            EncryptExistingData::dispatch('fetch')->onQueue($queueName);
            $this->info('Process initiated.');
        } else {
            $this->info('Process not initiated.');
        }
        
    }
}
