<?php

namespace App\Console\Commands;

use App\Http\Controllers\ErrorVisibilityController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenerateVisibilityReportData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generateReport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will generate the visibility report of each day and store in table';

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
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $cont = new ErrorVisibilityController();
        $data = $cont->getVisibilityReport(new Request(
            [
                'from' => now()->yesterday()->startOfDay()->format('Y-m-d\TH:i:s\Z'),
                'to' => now()->yesterday()->endOfDay()->format('Y-m-d\TH:i:s\Z'),
                'methods' => ['quote', 'revisedquote'],
                'show_query' => '',
            ]
        ));
        $error_type = $data->getOriginalContent()['status'] == false ? 'error' : 'info';
        Log::$error_type('Visibility Controller Response : ' . json_encode($data->getOriginalContent()) . 'Executed Queries => ' . json_encode(\Illuminate\Support\Facades\DB::getQueryLog()));
        $this->info('Visibility Report Data generated for yesterday : ' . now()->yesterday()->format('d-m-Y'));
        return 0;
    }
}
