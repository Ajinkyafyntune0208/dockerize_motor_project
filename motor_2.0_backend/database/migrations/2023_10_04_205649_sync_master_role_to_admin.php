<?php

use Illuminate\Database\Migrations\Migration;

class SyncMasterRoleToAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \Illuminate\Support\Facades\DB::table('permissions')->updateOrInsert([
            'name' => 'master.sync.fetch',
        ],
            [
                'guard_name' => 'web',
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

    }
}
