<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIcCredentialsInPermissionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       
            $data = array('ic_configurator.view','ic_configurator.credentials.editable');
            foreach ($data as $value) {
                DB::table('permissions')->updateOrInsert([
                    'name' => $value,
                    'guard_name' => 'web', 
                ],[
                    'created_at' => now(),
                    'updated_at' => now(),
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
        Schema::table('permission', function (Blueprint $table) {
            //
        });
    }
}
