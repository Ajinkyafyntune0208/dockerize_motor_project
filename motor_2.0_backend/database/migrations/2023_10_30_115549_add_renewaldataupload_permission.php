<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRenewaldatauploadPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('permissions')->updateOrInsert([ 
            'name' => 'renewal_data_upload.view'  
        ],
        [
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        DB::table('config_settings')->updateOrInsert([
            'label' => 'DASHBOARD_FETCH_USER_DETAILS',
            'key' => 'DASHBOARD_FETCH_USER_DETAILS',
            'value' => null,
        ],
        [
            'environment' => 'local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
        $role->syncPermissions(\Spatie\Permission\Models\Permission::all());
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
