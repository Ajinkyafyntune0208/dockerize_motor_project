<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class MenuAdminPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $slugs = DB::table('menu_master')->pluck('menu_slug');
        $actions = ['create', 'delete', 'edit', 'list', 'show'];

        foreach ($slugs as $slug) {
            foreach ($actions as $action) {

                $permissionName = $slug . '.' . $action;
                $permission = Permission::firstOrCreate(['name' => $permissionName],[
                    'guard_name' => 'web'
                ]);
                $adminRole->givePermissionTo($permission);  // Assign the permission to the Admin role
            }
        }
    }
}