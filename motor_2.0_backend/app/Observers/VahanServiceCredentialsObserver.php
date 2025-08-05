<?php

namespace App\Observers;

use App\Models\userActivityLog;
use App\Models\VahanServiceCredentials;

class VahanServiceCredentialsObserver
{
    /**
     * Handle the VahanServiceCredentials "created" event.
     *
     * @param  \App\Models\VahanServiceCredentials  $vahanServiceCredentials
     * @return void
     */
    public function created(VahanServiceCredentials $vahanServiceCredentials)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'VAHAN SERVICE CREDENTIALS',
            'table_name' => $vahanServiceCredentials->getTable(),
            'table_primary_id' => $vahanServiceCredentials->id,
            'new_data' => json_encode($vahanServiceCredentials->toArray()),
        ]);
    }

    /**
     * Handle the VahanServiceCredentials "updated" event.
     *
     * @param  \App\Models\VahanServiceCredentials  $vahanServiceCredentials
     * @return void
     */
    public function updated(VahanServiceCredentials $vahanServiceCredentials)
    {
        $differenceKeys = collect(array_keys(array_diff($vahanServiceCredentials->getOriginal(), $vahanServiceCredentials->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $vahanServiceCredentials) {
            $old_data[$key] = $vahanServiceCredentials->getOriginal()[$key] ?? null;
            $new_data[$key] = $vahanServiceCredentials->toArray()[$key] ?? null;
        });

        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'VAHAN SERVICE CREDENTIALS',
            'table_name' => $vahanServiceCredentials->getTable(),
            'table_primary_id' => $vahanServiceCredentials->id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
        ]);
    }

    /**
     * Handle the VahanServiceCredentials "deleted" event.
     *
     * @param  \App\Models\VahanServiceCredentials  $vahanServiceCredentials
     * @return void
     */
    public function deleted(VahanServiceCredentials $vahanServiceCredentials)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'VAHAN SERVICE CREDENTIALS',
            'table_name' => $vahanServiceCredentials->getTable(),
            'table_primary_id' => $vahanServiceCredentials->id,
            'old_data' => json_encode($vahanServiceCredentials->getOriginal()),
        ]);

    }

    /**
     * Handle the VahanServiceCredentials "restored" event.
     *
     * @param  \App\Models\VahanServiceCredentials  $vahanServiceCredentials
     * @return void
     */
    public function restored(VahanServiceCredentials $vahanServiceCredentials)
    {
        //
    }

    /**
     * Handle the VahanServiceCredentials "force deleted" event.
     *
     * @param  \App\Models\VahanServiceCredentials  $vahanServiceCredentials
     * @return void
     */
    public function forceDeleted(VahanServiceCredentials $vahanServiceCredentials)
    {
        //
    }
}
