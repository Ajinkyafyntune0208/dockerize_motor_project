<?php

namespace App\Observers;

use App\Models\MasterPolicy;
use App\Models\userActivityLog;

class MasterPolicyLogObserver
{
    /**
     * Handle the MasterPolicy "created" event.
     *
     * @param  \App\Models\MasterPolicy  $masterPolicy
     * @return void
     */
    public function created(MasterPolicy $masterPolicy)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id ?? 0,
            'operation' => 'CREATED',
            'service_type' => 'MASTER POLICY',
            'table_name' => $masterPolicy->getTable(),
            'table_primary_id' => $masterPolicy->policy_id,
            'new_data' => json_encode($masterPolicy->toArray())
        ]);
    }

    /**
     * Handle the MasterPolicy "updated" event.
     *
     * @param  \App\Models\MasterPolicy  $masterPolicy
     * @return void
     */
    public function updated(MasterPolicy $masterPolicy)
    {
        $differenceKeys = collect(array_keys(array_diff($masterPolicy->getOriginal(), $masterPolicy->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $masterPolicy) {
            $old_data[$key] = $masterPolicy->getOriginal()[$key] ?? null;
            $new_data[$key] = $masterPolicy->toArray()[$key] ?? null;
        });
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'MASTER POLICY',
            'table_name' => $masterPolicy->getTable(),
            'table_primary_id' => $masterPolicy->policy_id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
        ]);
    }

    /**
     * Handle the MasterPolicy "deleted" event.
     *
     * @param  \App\Models\MasterPolicy  $masterPolicy
     * @return void
     */
    public function deleted(MasterPolicy $masterPolicy)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'MASTER POLICY',
            'table_name' => $masterPolicy->getTable(),
            'table_primary_id' => $masterPolicy->policy_id,
            'old_data' => json_encode($masterPolicy->getOriginal())
        ]);
    }

    /**
     * Handle the MasterPolicy "restored" event.
     *
     * @param  \App\Models\MasterPolicy  $masterPolicy
     * @return void
     */
    public function restored(MasterPolicy $masterPolicy)
    {
        //
    }

    /**
     * Handle the MasterPolicy "force deleted" event.
     *
     * @param  \App\Models\MasterPolicy  $masterPolicy
     * @return void
     */
    public function forceDeleted(MasterPolicy $masterPolicy)
    {
        //
    }
}
