<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PolicySyncColumnChange extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_start_and_end_date_logs', function (Blueprint $table) {
            if (Schema::hasColumn('policy_start_and_end_date_logs', 'policy_id')) {
                $table->renameColumn('policy_id', 'policy_type');
            }
        });

        Schema::table('policy_start_and_end_date_logs', function (Blueprint $table) {
            $table->string('policy_type')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('policy_start_and_end_date_logs', function (Blueprint $table) {
            if (Schema::hasColumn('policy_start_and_end_date_logs', 'policy_type')) {
                $table->renameColumn('policy_type', 'policy_id');
            }
        });

        Schema::table('policy_start_and_end_date_logs', function (Blueprint $table) {
            $table->integer('policy_id')->change(); 
        });
    }
}

