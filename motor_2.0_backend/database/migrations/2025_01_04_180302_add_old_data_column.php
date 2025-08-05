<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOldDataColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_start_and_end_date_logs', function (Blueprint $table) {
            $table->json('old_data')->nullable()->after('policy_type');
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
            $table->dropColumn('old_data');
        });
    }
}
