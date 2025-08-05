<?php

namespace App\Providers;

use Illuminate\Support\Str;
use App\Models\VahanService;
use App\Models\ConfigSetting;
use App\Notifications\FailedJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use App\Models\VahanServiceCredentials;
use App\Observers\VahanServiceObserver;
use Illuminate\Support\ServiceProvider;
use App\Models\VahanServicePriorityList;
use App\Models\AgentDiscountConfiguration;
use Illuminate\Support\Facades\Notification;
use Laravel\Telescope\TelescopeServiceProvider;
use App\Observers\VahanServiceCredentialsObserver;
use App\Observers\VahanServicePriorityListObserver;
use App\Observers\AgentDiscountConfigurationObserver;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //VahanService::observe(VahanServiceObserver::class);
        //AgentDiscountConfiguration::observe(AgentDiscountConfigurationObserver::class);
        //VahanServiceCredentials::observe(VahanServiceCredentialsObserver::class);
        //VahanServicePriorityList::observe(VahanServicePriorityListObserver::class);
        Queue::failing(function (JobFailed $event) {
            if (!empty($failedSlackUrl = getCommonConfig('slack.failedJob.channel.url'))) {
                Notification::route('slack', $failedSlackUrl)->notify(new FailedJob($event));
            }
            if ($event->exception && !empty($event->exception->getMessage())) {
                \App\Models\MotorServerErrors::insert([
                    'url' => request()->path(),
                    'request' => json_encode(request()->all()),
                    'error' => $event->exception->getMessage(),
                    'source' => app()->runningInConsole() ? 'job-cron' : 'job-api',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
        
        \Illuminate\Support\Facades\DB::enableQueryLog();
        if (env('IS_SAAS')) {
            self::connectSaasDB();
        } else {
        if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
            $urlParts = explode('.', $_SERVER['HTTP_HOST']);
            $domains = ['api-unilight-uat', 'api-ola-uat', 'apimotor', 'api-ola-preprod', 'api-unilight-preprod', 'apimotoruat', 'apicarbike-sriyah-uat', 'apiabibl-carbike', 'apiabibl-preprod-carbike', 'apigramcover-carbike', 'apigramcover-carbike-preprod', 'uatapimotor', 'uatapicar', 'stageapimotor'];
            if (in_array($urlParts[0], $domains)) {
                $urlParts = explode('.', $_SERVER['HTTP_HOST']);
                DB::disconnect();
                Config::set('database.default', $urlParts[0]);
                DB::reconnect();
            }
        }
        }
        // if (!env('APP_FORCE_HTTP'))
        //     URL::forceScheme('https');
        /*  App::register('App\Providers\RenewBuyServiceProvider'); */
        # Force Scheme considering the current host
        URL::forceScheme( ( ( in_array( request()->getHost(), ["localhost", "127.0.0.1"] ) ) ? "http" : "https" ) );
        /*  App::register('App\Providers\RenewBuyServiceProvider'); */

        \Illuminate\Pagination\Paginator::useBootstrap();
        self::setConfig();

        \Illuminate\Support\Facades\Response::macro('pdfAttachment', function ($content, $contentType) {

            $headers = [
                // 'Content-type'        => 'application/pdf',
                'Content-type'        => $contentType,
                'Content-Disposition' => 'attachment; filename="download.pdf"',
            ];
        
            return \Illuminate\Support\Facades\Response::make($content, 200, $headers);
        
        });
    }

    /**
     * Set the config value to apllication services.
     *
     * @return void
     */
    static protected function setConfig()
    {
        $config = [];
        $has_table_config_settings = Cache::remember(request()->header('host') . '_has_table_config_settings', 3600, function () {
            return Schema::hasTable('config_settings');
        });

        if ($has_table_config_settings) {
            $configs = Cache::remember(request()->header('host') . '_config_settings', 3600, function () {
                return ConfigSetting::get(['key', 'value']);
            });
            
            foreach ($configs as $key => $value) {
                Config::set(trim($value->key), trim($value->value));
            }
        }

        setCommonConfigInCache();

        switch (config('app.env')) {
            case 'local':
                $env_folder = 'uat';
                break;

            case 'test':
                $env_folder = 'preprod';
                break;

            case 'live':
                $env_folder = 'production';
                break;
        }
        include_once app_path('Constants/'. $env_folder);
        foreach($config as $key => $value){
            Config::set(trim($key), trim($value));
        }

        Config::set('cache.prefix', (request()->getHost() ?? config('app.url')) . '_' . Str::slug(env('APP_NAME', 'laravel'), '_') . '_cache');
    }

    public static function connectSaasDB()
    {
        if (app()->runningInConsole()) {
            return;
        }
        try {
            $client = DB::connection('saas_sqlite')
            ->table('client_details')
            ->where([
                'host' => request()->getHost(),
                'key' => 'database'
            ])
                ->pluck('value')
                ->first();
            if (!empty($client)) {
                $client = json_decode($client, true);
                $host = str_replace('.', '-', $_SERVER['HTTP_HOST']);
                DB::disconnect();
                Config::set('database.default', $host);
                Config::set('database.connections.' . $host, $client);
                DB::reconnect();
            } else {
                Log::error("DB connection Failed..");
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }
}
