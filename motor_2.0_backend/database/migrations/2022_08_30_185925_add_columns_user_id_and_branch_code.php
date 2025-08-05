<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsUserIdAndBranchCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('cv_agent_mappings', 'branch_code')) //check the column
        {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->string('branch_code', 30)->nullable();
                $table->string('user_id', 20)->nullable();
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
        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->dropColumn('branch_code');
            $table->dropColumn('user_id');
        });
    }
}
