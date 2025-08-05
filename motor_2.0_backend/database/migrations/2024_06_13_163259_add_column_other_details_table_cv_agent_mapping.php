<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOtherDetailsTableCvAgentMapping extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('cv_agent_mappings') && !Schema::hasColumn('cv_agent_mappings', 'other_details')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->json('other_details')->nullable();
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
        //
    }
}
