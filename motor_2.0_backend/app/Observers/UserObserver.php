<?php

namespace App\Observers;

use App\Models\User;
use App\Models\userActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    public $afterCommit = true;

    /**
     * Handle the ConfigSetting "created" event.
     *
     * @param  \App\Models\User  $User
     * @return void
     */


     public function created(User $User)
     {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'USER',
            'table_name' => $User->getTable(),
            'table_primary_id' => $User->id,
            'new_data' => json_encode($User->toArray()),
        ]);
     }

     public function updated(User $User)
    {

        $differenceKeys = collect(array_keys(array_diff($User->getOriginal(), $User->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $User) {
            if ($key !== 'remember_token') {
                $old_data[$key] = $User->getOriginal()[$key] ?? null;
                $new_data[$key] = $User->$key ?? null;
            }
        });
        if (array_key_exists('remember_token', $new_data) && $new_data['remember_token'] !== null){
            unset($new_data['remember_token']);
        }
        if(!($new_data['password'] && count($new_data)===1)){
            userActivityLog::create([
                'user_id' => auth()->user()->id,
                'operation' => 'UPDATED',
                'service_type' => 'USER',
                'table_name' => $User->getTable(),
                'table_primary_id' => $User->id,
                'old_data' => json_encode($old_data),
                'new_data' => json_encode($new_data),
            ]);
        }
            
        
    }
}
