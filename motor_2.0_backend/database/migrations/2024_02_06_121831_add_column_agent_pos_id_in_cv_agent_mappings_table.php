<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAgentPosIdInCvAgentMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('cv_agent_mappings', 'agent_pos_id')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->string('agent_pos_id', 25)->after('seller_type')->nullable();
                $table->string('employee_pos_id', 25)->after('seller_type')->nullable();
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
        if (Schema::hasColumn('cv_agent_mappings', 'agent_pos_id')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->dropColumn('agent_pos_id');
                $table->dropColumn('employee_pos_id');
            });
        }
    }
}
