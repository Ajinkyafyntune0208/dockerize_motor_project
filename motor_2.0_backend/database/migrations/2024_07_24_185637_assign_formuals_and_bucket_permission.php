<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AssignFormualsAndBucketPermission extends Migration
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
                'name' => 'formuals.create',
            ],
            [
                'name' => 'formuals.delete',
            ],
            [
                'name' => 'formuals.edit'
            ],
            [
                'name' => 'formuals.list',
            ],
            [
                'name' => 'formuals.show',
            ],
    
            [
                'name' => 'buckets.create',
            ],
            [
                'name' => 'buckets.delete',
            ],
            [
                'name' => 'buckets.edit',
            ],
            [
                'name' => 'buckets.list',
            ],
            [
                'name' => 'buckets.show',
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
