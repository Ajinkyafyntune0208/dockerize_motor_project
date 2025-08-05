<?php

namespace App\Observers;

use App\Models\AgentDiscountConfiguration;
use App\Models\userActivityLog;

class AgentDiscountConfigurationObserver
{
    /**
     * Handle the AgentDiscountConfiguration "created" event.
     *
     * @param  \App\Models\AgentDiscountConfiguration  $agentDiscountConfiguration
     * @return void
     */
    public function created(AgentDiscountConfiguration $agentDiscountConfiguration)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'AGENT DISCOUNT CONFIGURATION',
            'table_name' => $agentDiscountConfiguration->getTable(),
            'table_primary_id' => $agentDiscountConfiguration->id,
            'new_data' => json_encode($agentDiscountConfiguration->toArray())
        ]);
    }

    /**
     * Handle the AgentDiscountConfiguration "updated" event.
     *
     * @param  \App\Models\AgentDiscountConfiguration  $agentDiscountConfiguration
     * @return void
     */
    public function updated(AgentDiscountConfiguration $agentDiscountConfiguration)
    {
        $differenceKeys = collect(array_keys(array_diff($agentDiscountConfiguration->getOriginal(), $agentDiscountConfiguration->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $agentDiscountConfiguration) {
            $old_data[$key] = $agentDiscountConfiguration->getOriginal()[$key] ?? null;
            $new_data[$key] = $agentDiscountConfiguration->toArray()[$key] ?? null;
        });
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'AGENT DISCOUNT CONFIGURATION',
            'table_name' => $agentDiscountConfiguration->getTable(),
            'table_primary_id' => $agentDiscountConfiguration->id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data)
        ]);
    }

    /**
     * Handle the AgentDiscountConfiguration "deleted" event.
     *
     * @param  \App\Models\AgentDiscountConfiguration  $agentDiscountConfiguration
     * @return void
     */
    public function deleted(AgentDiscountConfiguration $agentDiscountConfiguration)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'AGENT DISCOUNT CONFIGURATION',
            'table_name' => $agentDiscountConfiguration->getTable(),
            'table_primary_id' => $agentDiscountConfiguration->id,
            'old_data' => json_encode($agentDiscountConfiguration->getOriginal())
        ]);
    }

    /**
     * Handle the AgentDiscountConfiguration "restored" event.
     *
     * @param  \App\Models\AgentDiscountConfiguration  $agentDiscountConfiguration
     * @return void
     */
    public function restored(AgentDiscountConfiguration $agentDiscountConfiguration)
    {
        //
    }

    /**
     * Handle the AgentDiscountConfiguration "force deleted" event.
     *
     * @param  \App\Models\AgentDiscountConfiguration  $agentDiscountConfiguration
     * @return void
     */
    public function forceDeleted(AgentDiscountConfiguration $agentDiscountConfiguration)
    {
        //
    }
}
