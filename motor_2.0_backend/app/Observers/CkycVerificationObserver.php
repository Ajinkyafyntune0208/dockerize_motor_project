<?php

namespace App\Observers;

use App\Models\userActivityLog;
use App\Models\CkycVerificationTypes;

class CkycVerificationObserver
{
    /**
     * Handle the CkycVerificationTypes "created" event.
     *
     * @param  \App\Models\CkycVerificationTypes  $ckycVerificationTypes
     * @return void
     */
    public function created(CkycVerificationTypes $ckycVerificationTypes)
    {

        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'CkYC VERIFICATION TYPES',
            'table_name' => $ckycVerificationTypes->getTable(),
            'table_primary_id' => $ckycVerificationTypes->id,
            'new_data' => json_encode($ckycVerificationTypes->toArray())
        ]);
    }

    /**
     * Handle the CkycVerificationTypes "updated" event.
     *
     * @param  \App\Models\CkycVerificationTypes  $ckycVerificationTypes
     * @return void
     */
    public function updated(CkycVerificationTypes $ckycVerificationTypes)
    {
        $differenceKeys = collect(array_keys(array_diff($ckycVerificationTypes->getOriginal(), $ckycVerificationTypes->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $ckycVerificationTypes) {
            $old_data[$key] = $ckycVerificationTypes->getOriginal()[$key] ?? null;
            $new_data[$key] = $ckycVerificationTypes->toArray()[$key] ?? null;
            // dd($ckycVerificationTypes->getOriginal()[$key],$ckycVerificationTypes->toArray()[$key]);
        });
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'CkYC VERIFICATION TYPES',
            'table_name' => $ckycVerificationTypes->getTable(),
            'table_primary_id' => $ckycVerificationTypes->id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
        ]);
    }

    /**
     * Handle the CkycVerificationTypes "deleted" event.
     *
     * @param  \App\Models\CkycVerificationTypes  $ckycVerificationTypes
     * @return void
     */
    public function deleted(CkycVerificationTypes $ckycVerificationTypes)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'CkYC VERIFICATION TYPES',
            'table_name' => $ckycVerificationTypes->getTable(),
            'table_primary_id' => $ckycVerificationTypes->id,
            'old_data' => json_encode($ckycVerificationTypes->getOriginal())
        ]);
    }

    /**
     * Handle the CkycVerificationTypes "restored" event.
     *
     * @param  \App\Models\CkycVerificationTypes  $ckycVerificationTypes
     * @return void
     */
    public function restored(CkycVerificationTypes $ckycVerificationTypes)
    {
        //
    }

    /**
     * Handle the CkycVerificationTypes "force deleted" event.
     *
     * @param  \App\Models\CkycVerificationTypes  $ckycVerificationTypes
     * @return void
     */
    public function forceDeleted(CkycVerificationTypes $ckycVerificationTypes)
    {
        //
    }
}
