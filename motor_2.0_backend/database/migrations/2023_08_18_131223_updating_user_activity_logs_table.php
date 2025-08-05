<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdatingUserActivityLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->string('table_name')->after('service_type')->nullable();
            $table->string('commit_id', 36)->after('new_data')->nullable();
            $table->string('ip', 40)->after('commit_id')->nullable();
            $table->integer('table_primary_id')->after('table_name')->nullable();
            $table->index('user_id');
            $table->index('operation');
            $table->index('service_type');
            $table->index('table_name');
            $table->index('commit_id');
            $table->index('created_at');
        });

        DB::table('permissions')->updateOrInsert([
            'name' => 'log.user-activity',
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
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropColumn('table_name');
            $table->dropColumn('commit_id');
            $table->dropColumn('ip');
            $table->dropColumn('table_primary_id');
            $table->dropIndex('user_id');
            $table->dropIndex('operation');
            $table->dropIndex('service_type');
            $table->dropIndex('table_name');
            $table->dropIndex('commit_id');
            $table->dropIndex('created_at');
        });
    }
}
