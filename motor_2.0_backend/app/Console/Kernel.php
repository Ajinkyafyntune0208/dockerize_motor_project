<?php
namespace App\Console;
use App\Jobs\KotakPosRegistration;
use App\Jobs\IciciLombardPosRegistration;
use App\Http\Controllers\CommonController;
use App\Http\Controllers\Payment\ReHitPdfController;
use App\Jobs\BajajCrmDataPushJob;
use App\Jobs\FutureGeneraliPosRegistration;
use Illuminate\Console\Scheduling\Schedule;
use App\Jobs\IciciLombardBrekinStatusUpdate;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use App\Jobs\RenewalNotificationWhatsapp;
use App\Models\PasswordPolicy;
use Aws\Command;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\MMVDetails::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    { 
        
        $schedule->command("generatePolicyReport")->dailyAt('20:00');

        ## DELETE_USER_PROFILEING_LOG_PERIOD
        $schedule->command('clear:delete_user_profiling_log_period')->dailyAt('00:01');

        ## Policy Report Generation 
        $schedule->job(new \App\Jobs\PolicyReportDataPreparationJob())->dailyAt('00:00:01');

        ## Report Generation Cache
        $schedule->command("generateReport")->dailyAt('02:00')->onOneServer();
        # Premium Detail Report schedulers
        if (config('constants.motorConstant.ENABLE_PREMIUM_DETAIL_REPORT_CRON') == 'Y') {
            $schedule->job(new \App\Jobs\PremiumDetailErrorReport([
                'from' => date('Y-m-d 00:00:00', strtotime('yesterday')),
                'to' => date('Y-m-d 23:59:59', strtotime('yesterday'))
            ]))->dailyAt('01:00');
        }

        ##---
        ## Dashboard related schedulers -- start
            ## Log Summary - Visibility Report
            $schedule->command("save-log-summary")->everyTwoHours()->onOneServer();

            ## Offline Data migration
            $schedule->job(new \App\Jobs\RenewalMigrationProcessSingle)->dailyAt(config('constants.motor.RENEWAL_DATA_MIGRATION_SCHEDULAR_TIME', '02:00'));
            $schedule->job(new \App\Jobs\DataUploadMigrationV2)->dailyAt(config('constants.motor.RENEWAL_DATA_MIGRATION_SCHEDULAR_TIME', '02:00'));
            
            ## Sync current date data to Mongo
            $mongoDashboardEnabled = config('constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED');
            if( $mongoDashboardEnabled == "Y" )
            {
                $from_date = $to_date = now()->yesterday()->format('Y-m-d');
                $command_name = "dashboard:pusholddata " . $from_date . " " . $to_date . ' --ask_confirmation=false';
                $schedule->command($command_name)->dailyAt('01:00')->onOneServer()->name('Sync data to MongoDB');
            }
        ## Dashboard related schedulers -- start
        ##---

        ##---
        ## Schedulers related to POS Registration -- Start
            ## Get POS Agents list from Dashboard
            if(config('GET_AGENTS_API_ENABLE') == 'Y')
            { 
                ## Get the Agent Details in every two hours
                $schedule->call(function () { CommonController::getAgents(); } )->everyTwoHours();

                 ## ICICI POS SERVICE ENABLE
                if(config('ICICI_LOMBARD_POS_SERVICE_ENABLE') == 'Y')
                {
                    ## ICICI CRON
                    $schedule->job( new IciciLombardPosRegistration )->everyFiveMinutes();
                }

                ## Future Generali SERVICE ENABLE
                if(config('FUTURE_GENERALI_POS_SERVICE_ENABLE') == 'Y')
                {
                    ## Future Generali CRON
                    $schedule->job( new FutureGeneraliPosRegistration )->everyFiveMinutes();
                }

                ## Kotak SERVICE ENABLE
                if(config('KOTAK_POS_SERVICE_ENABLE') == 'Y')
                {
                    $schedule->job( new KotakPosRegistration )->everyFiveMinutes();
                }
            }
        ## Schedulers related to POS Registration -- end
        ##---
        $clientName = strtolower( config('constants.motorConstant.SMS_FOLDER') );
  
        if( $clientName == "ola" )
        { 
            # Emebedded Link and Scrub jobs
            $schedule->job(new \App\Jobs\EmbeddedLinkGeneration, 'embedded_link_generation')->everyTwoMinutes();
            $schedule->job(new \App\Jobs\EmbeddedLinkWhatsappMessageSending)->dailyAt(config('constants.motor.EMBEDDED_LINK_WHATSAPP_SCHEDULAR_TIME'));
            $schedule->job(new \App\Jobs\EmbeddedScrubDataGeneration, 'embedded_scrub_data_generation')->everyTwoMinutes();//between(config('constants.motor.EMBEDDED_SCRUB_DATA_GENERATION_SCHEDULAR_START_TIME'), config('constants.motor.EMBEDDED_SCRUB_DATA_GENERATION_SCHEDULAR_END_TIME'));

            ## Acko is there on OLA Only so moved in the OLA section
            if (config('constants.motor.FETCH_ACKO_CKYC_JOB_ENABLE') == 'Y')
            {
                $schedule->job(new \App\Jobs\FetchAckoCkycStatus)->everyThirtyMinutes();
            }

            if ( config('constants.motor.UPDATE_STATE_CITY_IN_EMBEDDED_LINK') == 'Y')
            {
                $schedule->job(new \App\Jobs\UpdateCityStateInUserProposal)->dailyAt('20:00');
            }
        }
        else if(  $clientName == "bajaj" )
        {
            $schedule->job(new BajajCrmDataPushJob)->everyFiveMinutes();
        }
        else if(  $clientName == "gramcover" )
        {
            $schedule->job(new \App\Jobs\GramcoverDataPushApiJob)->everyFifteenMinutes();
        }
        else if(  $clientName == "ace" )
        {
            $schedule->job(new RenewalNotificationWhatsapp)->everyFifteenMinutes()->between('10:00', '19:00');
        }
        else if(  $clientName == "abibl" )
        {
            //$schedule->job(new \App\Jobs\AbiblDailerJob)->dailyAt('00:01'); // Abibl dialer api on push data
            //$schedule->job(new \App\Jobs\AbiblDropoutJourneyJob)->everyMinute(); // Abibl dialer api on push data
            //$schedule->job(new \App\Jobs\AbiblRenewalJob)->everyFiveMinutes(); // Abibl Renewal Jobs  // commented due to duplicate entries
            
            # Regular, MG and Hyundai Active Whatsapp Notification
            $schedule->job(new \App\Jobs\HyundaiDataProcess)->everyThirtyMinutes()->between('00:05', '23:00');
            //$schedule->command("AbiblSendRenewalWhatsappNotification")->hourly()->between('11:00', '19:00')->onOneServer();
            
            # Hyundai Inactive Whatsapp Notification
            $schedule->job(new \App\Jobs\HyundaiInactiveDataProcess)->everyThirtyMinutes()->between('00:15', '23:15');
            //$schedule->command("AbiblSendRenewalWhatsappNotificationHyundaiInactive")->hourly()->between('11:05', '19:05')->onOneServer();
        }
        else if(  $clientName == "kmd" )
        { 
            $this->onePayRehitSchedule($schedule);
        }

        ## Inspection Status check
        $schedule->job(new \App\Jobs\InspectionConfirmJob)->everyTwoHours();

        ## Check the Payment Status and the policy documents of all the stuck cases.
        $this->rehitSchedule($schedule); 

        ## System Maintenance related - starts
            $schedule->job(new \App\Jobs\WebServiceInternalLogShareEmail)->dailyAt('00:01');
            $schedule->job(new \App\Jobs\DeleteServerLogRecord)->dailyAt('00:01');
        ## System Maintenance related - ends

        ##---
        ## CKYC Related Schedulers -- starts
            ## deleting directory where ckyc_photos are store for more than 7 days
            if(config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_PHOTOS_DELETION_ENABLED') == 'Y')
            {
                $schedule->command('clear:expired_ckyc_photos')->daily();
            } 

            ## Upload the CKYC Docs to SBI API
            if(config('SBI_RETRY_FAILED_DOC_UPLOAD') == "Y")
            {
                $schedule->job( new \App\Jobs\SbiDocumentUploadFailureJob )->dailyAt('00:01');
            }
        ## CKYC Related Schedulers -- starts
        ##---

        ## Password Policy, where we send password expiry notification to the created user.
        $schedule->job(new \App\Jobs\PasswordPolicyJob )->dailyAt('00:05');

        ##clear zip files from storage for Expoted vahan logs(rc Report)
        $schedule->command('VahanExportZipClear')->daily();

        if (config('constants.brokerConstant.ENABLE_IC_SAMPLING_SCHEDULAR') == 'Y') {
            $schedule->job(new \App\Jobs\IcSamplingJob)->dailyAt('00:01');
        }

        if (config('constants.brokerConstant.ENABLE_COMMISSION_RETROSPECTIVE_SCHEDULAR') == 'Y') {
            $schedule->job(new \App\Jobs\SyncBrokerage)->dailyAt('02:00');
        }
        if (config('constants.motorConstant.OFFLINE_VAHAN_DATA_UPLOAD') == 'Y') {
            $schedule->call(function () {
                \App\Jobs\VahanChunkFiles::dispatch();
            })->name('vahanChunakFiles')->withoutOverlapping()->everyMinute();
            
            $schedule->call(function () {
                \App\Jobs\vahanfileInsert::dispatch();
            })->name('vahanfileInsert')->withoutOverlapping()->everyMinute();

        //
            $schedule->call(function () {
                \App\Jobs\ProcessExcelExport::dispatch();
            })->name('ProcessExcelExport')->withoutOverlapping()->everyMinute();
            //
            $schedule->call(function () {
                \App\Jobs\DeleteOldVahanExcelFiles::dispatch();
            })->name('DeleteOldVahanExcelFiles')->withoutOverlapping()->everyMinute();
        }

        ## MMV Priority basis the business
        if(config('constants.motorConstant.MANUFACTURER_TOP_FIVE') == 'Y') {
            $schedule->command("manuf_mmv:cron")->dailyAt( '02:00' );
            ## Insert Version Count - Fetch Data
            $schedule->command("update:versioncount fetch")->dailyAt('03:00');
            ## Update Version Count - Update Details
            $schedule->command("update:versioncount update")->dailyAt('04:00');
            ## Update RTO Count for top five Priority
            $schedule->command("update:rtocount")->dailyAt('05:00');
        }

        ##Policy start date and end date updation
        $schedule->command('policy:update-dates')->dailyAt('00:30');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }

    public function onePayRehitSchedule(Schedule $schedule)
    {
        $schedule->call(function ()
        {
            \Illuminate\Support\Facades\Http::post(url('/api/onepay/rehitall'))->json();
        })->everyTwoHours();
    }

    public function rehitSchedule(Schedule $schedule)
    {
        ## Schedulers Running on Specific times during a day for last 2 days - Starts
        /*
        $schedule->call(function () {
            \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['date_range' => '2 days'])->json();
        })->dailyAt('00:05');

        $schedule->call(function () {
            \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['date_range' => '2 days'])->json();
        })->dailyAt('02:05');
        */

        $schedule->call(function () {
            \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['date_range' => '2 days'])->json();
        })->dailyAt('03:00');
        ## Schedulers Running on Specific times during a day for last 2 days - Ends

        /*
            ## Schedulers Running on Specific times during a day for current day - Starts
            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['date_range' => 'todays'])->json();
            })->dailyAt('11:05');

            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['date_range' => 'todays'])->json();
            })->dailyAt('15:05');

            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['date_range' => 'todays'])->json();
            })->dailyAt('19:05');
            ## Schedulers Running on Specific times during a day for current day - End
        */

        $schedule->call(function () {
            \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'))->json();
        })->everyTwoHours(); 

        $schedule->call(function () {
            \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['time' => '30 minutes'])->json();
        })->everyMinute();

        /*
            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['time' => '5 minutes'])->json();
            })->everyFiveMinutes();
            
            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['time' => '15 minutes'])->json();
            })->everyMinute();
            
            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), ['time' => '120 minutes'])->json();
            })->everyMinute(); 
        */

        ##---
        ## Schedulers Running for failure cases - Start
            ## Run on everyday at 00:01
            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), [
                    'processing_type' => 'failure_cases',
                    'process_range' => 1, // Last 1 day
                ])->json();
            })->name('rehit:last_one_day')->dailyAt('02:00');
            
            ## Run on every monday at 00:01
            $schedule->call(function () {
                \Illuminate\Support\Facades\Http::post(url('/api/generatePdfAll'), [
                    'processing_type' => 'failure_cases',
                    'process_range' => 7, // Last 7 days
                ])->json();
            })->name('rehit:last_7_days')->weeklyOn(1, '03:00');
        ## Schedulers Running for failure cases - End
        ##---
    }
}