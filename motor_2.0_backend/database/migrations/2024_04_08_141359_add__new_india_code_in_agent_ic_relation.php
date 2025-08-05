<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewIndiaCodeInAgentIcRelation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('agent_ic_relationship', 'new_india_code'))
        {
        Schema::table('agent_ic_relationship', function (Blueprint $table) {
                $table->string('new_india_code')->nullable();
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
        if (Schema::hasColumn('agent_ic_relationship', 'new_india_code')) {
            Schema::table('agent_ic_relationship', function (Blueprint $table) {
                $table->dropColumn('new_india_code');
            });
    }
}
}
