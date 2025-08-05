<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsAadharNoPanNoCvAgentMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->string('aadhar_no', 20)->after('agent_email')->nullable();
            $table->string('pan_no', 20)->after('aadhar_no')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->dropColumn('aadhar_no');
            $table->dropColumn('pan_no');
        });
    }
}
