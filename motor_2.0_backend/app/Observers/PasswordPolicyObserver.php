<?php

namespace App\Observers;

use App\Models\PasswordPolicy;
use App\Models\userActivityLog;

class PasswordPolicyObserver
{
    /**
     * Handle the PasswordPolicy "created" event.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return void
     */
    public function created(PasswordPolicy $passwordPolicy)
    {
            userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'PASSWORD POLICY',
            'table_name' => $passwordPolicy->getTable(),
            'table_primary_id' => $passwordPolicy->id,
            'new_data' => json_encode($passwordPolicy->toArray())
        ]);
    }
    

    /**
     * Handle the PasswordPolicy "updated" event.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return void
     */
    public function updated(PasswordPolicy $passwordPolicy)
    {
        $differenceKeys = collect(array_keys(array_diff($passwordPolicy->getOriginal(), $passwordPolicy->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $passwordPolicy) {
            $old_data[$key] = $passwordPolicy->getOriginal()[$key] ?? null;
            $new_data[$key] = $passwordPolicy->toArray()[$key] ?? null;  
            // dd( collect(array_keys(array_diff($passwordPolicy->getOriginal(), $passwordPolicy->toArray()))),$passwordPolicy->getOriginal(),$passwordPolicy->toArray());
        });
            userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'PASSWORD POLICY',
            'table_name' => $passwordPolicy->getTable(),
            'table_primary_id' => $passwordPolicy->id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
        ]);

    }

    /**
     * Handle the PasswordPolicy "deleted" event.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return void
     */
    public function deleted(PasswordPolicy $passwordPolicy)
    {
        //
    }

    /**
     * Handle the PasswordPolicy "restored" event.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return void
     */
    public function restored(PasswordPolicy $passwordPolicy)
    {
        //
    }

    /**
     * Handle the PasswordPolicy "force deleted" event.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return void
     */
    public function forceDeleted(PasswordPolicy $passwordPolicy)
    {
        //
    }
}
