<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUseRolerPermissionsData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = array('master_occupation.list','master_occuption_name.list','third_party.list','previous_insurer_mappping.list','preferred_rto.list','addon_configuration.list','financing_agreement.list','nominee_relationship.list','gender_mapping.list','abibl_mg_data.list','abibl_old_data.list','abibl_hyundai_data.list');
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
