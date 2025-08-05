<?php

namespace App\Console\Commands;

use App\Http\Controllers\PolicyStartAndEndDateUpdate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdatePolicyDatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:update-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Policy start/end date as well as TP start date and end date';

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
         // Set date range for yesterday
         $from = Carbon::yesterday()->toDateString();
         $to = Carbon::yesterday()->toDateString();
 
         // Create a fake request object
         $request = new \Illuminate\Http\Request();
         $request->merge([
             'from' => $from,
             'to' => $to,
             'checksum' => 'HGFDF@#$%^&878454545#@%$#@GJG$#$%^%$#45454544544^%%#$#%$^$$4HGG@#$%^&(*&^%' // Same as in controller
         ]);
 
         // Call the controller method
         $controller = new PolicyStartAndEndDateUpdate();
         $response = $controller->updatePolicyDates($request);
 
         $this->info('Policy dates updated successfully!');
     }
}
