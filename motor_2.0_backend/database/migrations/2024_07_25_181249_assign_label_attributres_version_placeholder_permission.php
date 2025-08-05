<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AssignLabelAttributresVersionPlaceholderPermission extends Migration
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
                'name' => 'label_and_attribute.list',
            ],
            [
                'name' => 'label_and_attribute.show',
            ],
            [
                'name' => 'label_and_attribute.create'
            ],
            [
                'name' => 'label_and_attribute.edit',
            ],
            [
                'name' => 'label_and_attribute.delete',
            ],
            
    
            [
                'name' => 'ic_placeholder.list',
            ],
            [
                'name' => 'ic_placeholder.delete',
            ],
            [
                'name' => 'ic_placeholder.edit',
            ],
            [
                'name' => 'ic_placeholder.create',
            ],
            [
                'name' => 'ic_placeholder.show',
            ],


            [
                'name' => 'ic_version_configurator.list',
            ],
            [
                'name' => 'ic_version_configurator.delete',
            ],
            [
                'name' => 'ic_version_configurator.edit',
            ],
            [
                'name' => 'ic_version_configurator.create',
            ],
            [
                'name' => 'ic_version_configurator.show',
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
