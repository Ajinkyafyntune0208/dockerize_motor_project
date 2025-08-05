<?php

namespace App\Observers;

use App\Models\userActivityLog;
use App\Models\VahanServicePriorityList;

class VahanServicePriorityListObserver
{
    /**
     * Handle the VahanServicePriorityList "created" event.
     *
     * @param  \App\Models\VahanServicePriorityList  $vahanServicePriorityList
     * @return void
     */
    public function created(VahanServicePriorityList $vahanServicePriorityList)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'VAHAN SERVICE CONFIGURATION',
            'table_name' => $vahanServicePriorityList->getTable(),
            'table_primary_id' => $vahanServicePriorityList->id,
            'new_data' => json_encode($vahanServicePriorityList->toArray())
        ]);
    }

    /**
     * Handle the VahanServicePriorityList "updated" event.
     *
     * @param  \App\Models\VahanServicePriorityList  $vahanServicePriorityList
     * @return void
     */
    public function updated(VahanServicePriorityList $vahanServicePriorityList)
    {
        $differenceKeys = collect(array_keys(array_diff($vahanServicePriorityList->getOriginal(), $vahanServicePriorityList->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $vahanServicePriorityList) {
            $old_data[$key] = $vahanServicePriorityList->getOriginal()[$key] ?? null;
            $new_data[$key] = $vahanServicePriorityList->toArray()[$key] ?? null;
        });
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'VAHAN SERVICE CONFIGURATION',
            'table_name' => $vahanServicePriorityList->getTable(),
            'table_primary_id' => $vahanServicePriorityList->id,
            'old_data' => json_encode($vahanServicePriorityList->getOriginal()),
            'new_data' => json_encode($vahanServicePriorityList->toArray())
        ]);
    }

    /**
     * Handle the VahanServicePriorityList "deleted" event.
     *
     * @param  \App\Models\VahanServicePriorityList  $vahanServicePriorityList
     * @return void
     */
    public function deleted(VahanServicePriorityList $vahanServicePriorityList)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'VAHAN SERVICE CONFIGURATION',
            'table_name' => $vahanServicePriorityList->getTable(),
            'table_primary_id' => $vahanServicePriorityList->id,
            'old_data' => json_encode($vahanServicePriorityList->getOriginal())
        ]);
    }

    /**
     * Handle the VahanServicePriorityList "restored" event.
     *
     * @param  \App\Models\VahanServicePriorityList  $vahanServicePriorityList
     * @return void
     */
    public function restored(VahanServicePriorityList $vahanServicePriorityList)
    {
        //
    }

    /**
     * Handle the VahanServicePriorityList "force deleted" event.
     *
     * @param  \App\Models\VahanServicePriorityList  $vahanServicePriorityList
     * @return void
     */
    public function forceDeleted(VahanServicePriorityList $vahanServicePriorityList)
    {
        //
    }
}
