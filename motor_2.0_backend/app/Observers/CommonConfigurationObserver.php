<?php

namespace App\Observers;

use App\Models\CommonConfigurations;
use App\Models\userActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;

class CommonConfigurationObserver
{
    public $afterCommit = true;

    /**
     * Handle the ConfigSetting "created" event.
     *
     * @param  \App\Models\CommonConfigurations  $CommonConfigurations
     * @return void
     */

     public function created(CommonConfigurations $CommonConfigurations)
     {
        $url = url()->previous();
        $session_id = session()->getId();
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'COMMON CONFIGURATION',
            'table_name' => $CommonConfigurations->getTable(),
            'table_primary_id' => $CommonConfigurations->id,
            'new_data' => json_encode($CommonConfigurations->toArray()),
            'trace_url'=> $url,
            'session_id'=>$session_id
        ]);
        Cache::forget(request()->header('host') . '_common_configurations_all');
        setCommonConfigInCache();
     }

     public function updated(CommonConfigurations $CommonConfigurations)
    {
        $differenceKeys = collect(array_keys(array_diff($CommonConfigurations->getOriginal(), $CommonConfigurations->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $CommonConfigurations) {
            $old_data[$key] = $CommonConfigurations->getOriginal()[$key] ?? null;
            $new_data[$key] = $CommonConfigurations->toArray()[$key] ?? null;
        });
        $url = url()->previous();
        $session_id = session()->getId();
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'COMMON CONFIGURATION',
            'table_name' => $CommonConfigurations->getTable(),
            'table_primary_id' => $CommonConfigurations->id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
            'trace_url'=> $url,
            'session_id'=>$session_id
        ]);
        Cache::forget(request()->header('host') . '_common_configurations_all');
        setCommonConfigInCache();
    }
}
