<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColmnCvAgentMapping extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->string('relation_hdfc_ergo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('cv_agent_mappings', 'relation_hdfc_ergo')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->dropColumn('relation_hdfc_ergo');
            });
        }
    }
}
