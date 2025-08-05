<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRoleAndPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data =array('ckyc_log.list','ckyc_wrapper_log.list','kafka_log.list','third_paty_payment.list','push_api.list','icici_master.list','error_master.list','trace_journey_id.list','third_party_api.list','mongodb.list','onepay_log.list','ongrid_fastlane.list','ola_whatsapp_log.list');
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
        //
    }
}
