<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use App\Models\ConfigSetting;
use App\Models\User;

class UserTableExists extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       
        $defaultConfigs = [
            'WEBSERVICE.ROLE' => 'webservice',
            'WEBSERVICE.USER.EMAIL' => 'webservice@fyntune.com',
            'WEBSERVICE.USER.NAME' => 'WebserviceUser',
        ];

       
        foreach ($defaultConfigs as $key => $defaultValue) {
            $config = ConfigSetting::where('key', $key)->first();
            if (!$config) {
                ConfigSetting::create([
                    'key' => $key,
                    'label' => $key,
                    'value' => $defaultValue,
                    'environment' => env('APP_ENV') ?? ""
                ]);
            }
        }

      
        $roleName = ConfigSetting::where('key', 'WEBSERVICE.ROLE')->value('value');
        $userEmail = ConfigSetting::where('key', 'WEBSERVICE.USER.EMAIL')->value('value');
        $userName = ConfigSetting::where('key', 'WEBSERVICE.USER.NAME')->value('value');

      
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            Role::create(['name' => $roleName]);
        }

      
        $user = User::where('email', $userEmail)->first();
        if (!$user) {
            $user = User::create([
                'email' => $userEmail,
                'name' => $userName,
                'password' => Illuminate\Support\Facades\Hash::make("fd6X6a5C#Nde$791"),
            ]);
            $user->assignRole($roleName);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       
        $roleName = ConfigSetting::where('key', 'WEBSERVICE.ROLE')->value('value');
        $userEmail = ConfigSetting::where('key', 'WEBSERVICE.USER.EMAIL')->value('value');

    
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $role->delete();
        }

      
        $user = User::where('email', $userEmail)->first();
        if ($user) {
            $user->delete();
        }

        ConfigSetting::whereIn('key', [
            'WEBSERVICE.ROLE',
            'WEBSERVICE.USER.EMAIL',
            'WEBSERVICE.USER.NAME',
        ])->delete();
    }
}
