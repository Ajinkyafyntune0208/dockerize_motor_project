<?php

use Database\Seeders\MenuMasterSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RunSeedersPermissionsAndMenuMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('permissions')) {
            $permissionSeeder = new PermissionsSeeder();
            $permissionSeeder->run();
        }

        if (Schema::hasTable('menu_master')) {
            $menuMasterSeeder = new MenuMasterSeeder();
            $menuMasterSeeder->run();
        }

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
        $role->syncPermissions(\Spatie\Permission\Models\Permission::all());
    }

    /**
     * Reverse the migrations.s
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
