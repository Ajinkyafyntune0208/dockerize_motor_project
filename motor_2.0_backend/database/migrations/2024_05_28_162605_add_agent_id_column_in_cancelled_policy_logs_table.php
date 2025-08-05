<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAgentIdColumnInCancelledPolicyLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('cancelled_policy_logs') && !Schema::hasColumn('cancelled_policy_logs','agent_id')) {
                Schema::table('cancelled_policy_logs', function (Blueprint $table) {
                    $table->integer('agent_id')->nullable();
                });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cancelled_policy_logs', function (Blueprint $table) {
            //
        });
    }
}