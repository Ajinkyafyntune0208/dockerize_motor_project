<?php

namespace App\Providers;

use App\Helpers\ApiTimeoutHelper;
use Illuminate\Support\ServiceProvider;

class ApiTimeoutServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ApiTimeoutHelper', function () {
            return new ApiTimeoutHelper();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
