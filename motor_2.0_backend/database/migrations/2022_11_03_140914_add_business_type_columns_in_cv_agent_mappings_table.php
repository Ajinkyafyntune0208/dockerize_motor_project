<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBusinessTypeColumnsInCvAgentMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->string('agent_business_type')->nullable();
            $table->string('agent_business_code')->nullable();
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
            $table->dropColumn('agent_business_type');
            $table->dropColumn('agent_business_code');
        });
    }
}
