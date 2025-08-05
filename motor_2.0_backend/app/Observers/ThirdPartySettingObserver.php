<?php

namespace App\Observers;

use App\Models\ThirdPartySetting;
use App\Models\userActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ThirdPartySettingObserver
{
    public $afterCommit = true;

    /**
     * Handle the ConfigSetting "created" event.
     *
     * @param  \App\Models\ThirdPartySetting  $ThirdPartySetting
     * @return void
     */
    public function created(ThirdPartySetting $ThirdPartySetting)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'THIRD PARTY SETTINGS',
            'table_name' => $ThirdPartySetting->getTable(),
            'table_primary_id' => $ThirdPartySetting->id,
            'new_data' => json_encode($ThirdPartySetting->toArray()),
        ]);
    }

    public function updated(ThirdPartySetting $ThirdPartySetting)
    {
        $newData = $ThirdPartySetting->toArray();
        $oldData = $ThirdPartySetting->getOriginal();
        if (!empty($ThirdPartySetting->toArray()['headers'])) {
            $newData['headers'] = json_encode($newData['headers']);
        }
        if (!empty($ThirdPartySetting->getOriginal()['headers'])) {
            $oldData['headers'] = json_encode($oldData['headers']);
        }
        $differenceKeys = collect(array_keys(array_diff($oldData, $newData)));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $newData, $oldData) {
            $old_data[$key] = $oldData[$key] ?? null;
            $new_data[$key] = $newData[$key] ?? null;
        });
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'THIRD PARTY SETTINGS',
            'table_name' => $ThirdPartySetting->getTable(),
            'table_primary_id' => $ThirdPartySetting->id,
            'old_data' => json_encode($old_data),
            'new_data' =>json_encode ($new_data),
        ]);
    }

    public function deleted(ThirdPartySetting $ThirdPartySetting)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'THIRD PARTY SETTINGS',
            'table_name' => $ThirdPartySetting->getTable(),
            'table_primary_id' => $ThirdPartySetting->id,
            'old_data' => json_encode($ThirdPartySetting->getOriginal()),
        ]);
    }

}
