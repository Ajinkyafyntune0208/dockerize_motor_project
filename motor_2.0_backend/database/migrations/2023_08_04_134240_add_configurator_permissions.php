<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfiguratorPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = ['configurator.proposal', 'configurator.field','configurator.onboarding','configurator.OTP'];
        
        foreach ($data as $dat) {
            DB::table('permissions')->updateOrInsert([ 
                'name' => $dat    
            ],
            [
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
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
