<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MMVDetailsService;

class MMVDetailsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MMVDetailsService::class, function ($app) {
            return new MMVDetailsService();
        });
    }

    public function boot()
    {
        //
    }
}
