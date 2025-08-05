<?php

namespace App\Traits;

use App\Models\ConfigSetting;
use App\Models\userActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

trait ActivityTrait
{
    /**
     * Log activity for the model.
     *
     * @param string $operation
     * @param string $serviceType
     * @param array $newData
     * @return void
     */
    public function logActivity($operation, $serviceType, $newData = [])
    {
        $url = url()->previous();
        $session_id = session()->getId();

        userActivityLog::create([
            //'user_id' => app()->runningInConsole() ? 0 :auth()->user()->id,
            // 'user_id' => 17,
            'user_id' => getUserId(),
            'operation' => $operation,
            'service_type' => $serviceType,
            'table_name' => $this->getTable(), 
            'table_primary_id' => $this->id??$this->menu_id, 
            'new_data' => json_encode($newData),
            'trace_url'=> $url,
            'session_id'=>$session_id
        ]);
    }

    public function logUpdateActivity($operation,$oldData, $newData, $serviceType)
    {
        $url = url()->previous();
        $session_id = session()->getId();
        userActivityLog::create([
            //'user_id' => app()->runningInConsole() ? 0 :auth()->user()->id,
            // 'user_id' => 17,
            'user_id' => getUserId(),
            'operation' => $operation,
            'service_type' => $serviceType,
            'table_name' => $this->getTable(),
            'table_primary_id' => $this->id??$this->menu_id,
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
            'trace_url'=> $url,
            'session_id'=>$session_id
        ]);
    }
    
    public function logDeletedActivity($operation, $serviceType, $oldData)
    {
        $url = url()->previous();
        $session_id = session()->getId();
        userActivityLog::create([
            //'user_id' => app()->runningInConsole() ? 0 :auth()->user()->id,
            // 'user_id' => 17,
            'user_id' => getUserId(),
            'operation' => $operation,
            'service_type' => $serviceType,
            'table_name' => $this->getTable(), 
            'table_primary_id' => $this->id??$this->menu_id, 
            'old_data' => json_encode($oldData),
            'trace_url'=> $url,
            'session_id'=>$session_id
        ]);
    }
    
    public function configCacheClear(){
        Cache::forget(request()->header('host') . '_config_settings');
        $configs = Cache::remember(request()->header('host') . '_config_settings', 3600, function () {
            return ConfigSetting::get(['key', 'value']);
        });

        foreach ($configs as $key => $value) {
            Config::set($value->key, $value->value);
        }
    }

}
