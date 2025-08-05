<?php

namespace App\Observers;

use App\Models\PospUtility;
use App\Models\userActivityLog;

class PolicyUtilityLogObserver
{
    /**
     * Handle the MasterPolicy "created" event.
     *
     * @param  \App\Models\MasterPolicy  $pospUtility
     * @return void
     */
    public function created(PospUtility $pospUtility)
    {
        userActivityLog::create([
            'user_id' =>  0,
            'operation' => 'CREATED',
            'service_type' => 'POSP UTILITY',
            'table_name' => $pospUtility->getTable(),
            'table_primary_id' => $pospUtility->utility_id,
            'new_data' => json_encode($pospUtility->toArray())
        ]);
    }

    /**
     * Handle the MasterPolicy "updated" event.
     *
     * @param  \App\Models\PospUtility  $masterPolicy
     * @return void
     */
    public function updated(PospUtility $pospUtility)
    {
        $differenceKeys = collect(array_keys(array_diff($pospUtility->getOriginal(), $pospUtility->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $pospUtility) {
            $old_data[$key] = $pospUtility->getOriginal()[$key] ?? null;
            $new_data[$key] = $pospUtility->toArray()[$key] ?? null;
        });
        userActivityLog::create([
            'user_id' => 0,
            'operation' => 'UPDATED',
            'service_type' => 'POSP UTILITY',
            'table_name' => $pospUtility->getTable(),
            'table_primary_id' => $pospUtility->utility_id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
        ]);
    }

    /**
     * Handle the MasterPolicy "deleted" event.
     *
     * @param  \App\Models\PospUtility  $pospUtility
     * @return void
     */
    public function deleted(PospUtility $pospUtility)
    {
        userActivityLog::create([
            'user_id' => 0,
            'operation' => 'DELETED',
            'service_type' => 'POSP UTILITY',
            'table_name' => $pospUtility->getTable(),
            'table_primary_id' => $pospUtility->utility_id,
            'old_data' => json_encode($pospUtility->getOriginal())
        ]);
    }

    /**
     * Handle the MasterPolicy "restored" event.
     *
     * @param  \App\Models\PospUtility  $pospUtility
     * @return void
     */
    public function restored(PospUtility $pospUtility)
    {
        //
    }

    /**
     * Handle the MasterPolicy "force deleted" event.
     *
     * @param  \App\Models\PospUtility  $pospUtility
     * @return void
     */
    public function forceDeleted(PospUtility $pospUtility)
    {
        //
    }
}
