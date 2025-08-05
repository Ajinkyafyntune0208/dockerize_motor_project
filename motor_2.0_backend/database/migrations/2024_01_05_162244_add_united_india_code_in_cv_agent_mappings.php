<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnitedIndiaCodeInCvAgentMappings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('cv_agent_mappings', 'relation_united_india')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->string('relation_united_india')->nullable();
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
        if (Schema::hasColumn('cv_agent_mappings', 'relation_united_india')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->dropColumn('relation_united_india');
            });
        }
    }
}