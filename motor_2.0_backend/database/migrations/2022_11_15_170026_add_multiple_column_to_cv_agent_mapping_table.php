<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMultipleColumnToCvAgentMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->string('branch_name')->nullable();
            $table->string('channel_id')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('region_name')->nullable();
            $table->string('region_id')->nullable();
            $table->string('zone_id')->nullable();
            $table->string('zone_name')->nullable();
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
            $table->dropColumn(['branch_name', 'channel_id', 'channel_name', 'region_name', 'region_id', 'zone_id', 'zone_name']);
        });
    }
}
