<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPermissionsData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $data = array('ic-error-handling.list', 'pos.list', 'encrypt-decrypt.list', 'payment.list', 'rto_master.list');
        foreach ($data as $value) {
            DB::table('permissions')->updateOrInsert([
                'name' => $value,
                'guard_name' => 'web',
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
        //
    }
}
