<?php

namespace App\Observers;

use App\Models\ConfigSetting;
use App\Models\userActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ConfigObserver
{

    public $afterCommit = true;

    /**
     * Handle the ConfigSetting "created" event.
     *
     * @param  \App\Models\ConfigSetting  $configSetting
     * @return void
     */
    public function created(ConfigSetting $configSetting)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'CONFIG SETTINGS',
            'table_name' => $configSetting->getTable(),
            'table_primary_id' => $configSetting->id,
            'new_data' => json_encode($configSetting->toArray()),
        ]);
        Cache::forget(request()->header('host') . '_config_settings');

        $configs = Cache::remember(request()->header('host') . '_config_settings', 3600, function () {
            return ConfigSetting::get(['key', 'value']);
        });

        foreach ($configs as $key => $value) {
            Config::set($value->key, $value->value);
        }
    }

    /**
     * Handle the ConfigSetting "updated" event.
     *
     * @param  \App\Models\ConfigSetting  $configSetting
     * @return void
     */
    public function updated(ConfigSetting $configSetting)
    {
        $differenceKeys = collect(array_keys(array_diff($configSetting->getOriginal(), $configSetting->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $configSetting) {
            $old_data[$key] = $configSetting->getOriginal()[$key] ?? null;
            $new_data[$key] = $configSetting->toArray()[$key] ?? null;
        });
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'CONFIG SETTINGS',
            'table_name' => $configSetting->getTable(),
            'table_primary_id' => $configSetting->id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
        ]);
        Cache::forget(request()->header('host') . '_config_settings');

        $configs = Cache::remember(request()->header('host') . '_config_settings', 3600, function () {
            return ConfigSetting::get(['key', 'value']);
        });

        foreach ($configs as $key => $value) {
            Config::set($value->key, $value->value);
        }
    }

    /**
     * Handle the ConfigSetting "deleted" event.
     *
     * @param  \App\Models\ConfigSetting  $configSetting
     * @return void
     */
    public function 
    
    
    deleted(ConfigSetting $configSetting)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'CONFIG SETTINGS',
            'table_name' => $configSetting->getTable(),
            'table_primary_id' => $configSetting->id,
            'old_data' => json_encode($configSetting->getOriginal()),
        ]);
        Cache::forget(request()->header('host') . '_config_settings');

        $configs = Cache::remember(request()->header('host') . '_config_settings', 3600, function () {
            return ConfigSetting::get(['key', 'value']);
        });

        foreach ($configs as $key => $value) {
            Config::set($value->key, $value->value);
        }
    }

    /**
     * Handle the ConfigSetting "restored" event.
     *
     * @param  \App\Models\ConfigSetting  $configSetting
     * @return void
     */
    public function restored(ConfigSetting $configSetting)
    {
        Cache::forget(request()->header('host') . '_config_settings');

        $configs = Cache::remember(request()->header('host') . '_config_settings', 3600, function () {
            return ConfigSetting::get(['key', 'value']);
        });

        foreach ($configs as $key => $value) {
            Config::set($value->key, $value->value);
        }
    }

    /**
     * Handle the ConfigSetting "force deleted" event.
     *
     * @param  \App\Models\ConfigSetting  $configSetting
     * @return void
     */
    public function forceDeleted(ConfigSetting $configSetting)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'CONFIG SETTINGS',
            'table_name' => $configSetting->getTable(),
            'table_primary_id' => $configSetting->id,
            'old_data' => json_encode($configSetting->getOriginal()),
        ]);
        Cache::forget(request()->header('host') . '_config_settings');

        $configs = Cache::remember(request()->header('host') . '_config_settings', 3600, function () {
            return ConfigSetting::get(['key', 'value']);
        });

        foreach ($configs as $key => $value) {
            Config::set($value->key, $value->value);
        }
    }
}
