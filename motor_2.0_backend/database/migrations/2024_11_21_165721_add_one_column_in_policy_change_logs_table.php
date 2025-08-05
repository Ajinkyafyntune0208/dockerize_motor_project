<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOneColumnInPolicyChangeLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('policy_change_logs')) {
            if (!Schema::hasColumn('policy_change_logs', 'source')) {
                Schema::table('policy_change_logs', function (Blueprint $table) {
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
        Schema::table('policy_change_logs', function (Blueprint $table) {
            //
        });
    }
}
