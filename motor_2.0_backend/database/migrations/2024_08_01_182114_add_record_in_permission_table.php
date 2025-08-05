<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecordInPermissionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
            $permissions = [

                [
                    'name' => 'configurator.field',
                ],
                [
                    'name' => 'configurator.proposal',
                ],
                [
                    'name' => 'configurator.onboarding'
                ],
                [
                    'name' => 'configurator.OTP',
                ]

            ];

            foreach ($permissions as $permission) {
                \Spatie\Permission\Models\Permission::firstOrCreate($permission);
            }

            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
            $role->syncPermissions(\Spatie\Permission\Models\Permission::all());
            \App\Models\User::first()->assignRole($role);
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
