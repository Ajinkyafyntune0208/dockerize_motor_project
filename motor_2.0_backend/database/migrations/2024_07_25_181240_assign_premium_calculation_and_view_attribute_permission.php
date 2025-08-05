<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AssignPremiumCalculationAndViewAttributePermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        $permissions = [
            [
                'name' => 'premium_calculation_configurator.create',
            ],
            [
                'name' => 'premium_calculation_configurator.delete',
            ],
            [
                'name' => 'premium_calculation_configurator.edit'
            ],
            [
                'name' => 'premium_calculation_configurator.list',
            ],
            [
                'name' => 'premium_calculation_configurator.show',
            ],
    
            [
                'name' => 'view_attributes.create',
            ],
            [
                'name' => 'view_attributes.delete',
            ],
            [
                'name' => 'view_attributes.edit',
            ],
            [
                'name' => 'view_attributes.list',
            ],
            [
                'name' => 'view_attributes.show',
            ],
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
        //
    }
}
