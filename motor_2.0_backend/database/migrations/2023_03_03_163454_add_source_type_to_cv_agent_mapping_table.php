<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceTypeToCvAgentMappingTable extends Migration
{
    /**
     * Run the migrations.
     * Added Source Type Column for Dashboard Requirement
     * There is Already Source Column to Avoid Any Unintended Issues New Column Created
     * @return void
     */

 
    public function up()
    {
        if (!Schema::hasColumn('cv_agent_mappings', 'source_type')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->string('source_type')->nullable();
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
        if (Schema::hasColumn('cv_agent_mappings', 'source_type')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->dropColumn('source_type');
            });
        }
    }
}
