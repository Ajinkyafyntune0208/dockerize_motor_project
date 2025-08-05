<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOneColumnInPolicyUpdateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (Schema::hasTable('policy_update_logs')) {
            if (!Schema::hasColumn('policy_update_logs', 'source')) {
                Schema::table('policy_update_logs', function (Blueprint $table) {
                    $table->string('source')->after('screenshot_url');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('policy_update_logs', function (Blueprint $table) {
            //
        });
    }
}