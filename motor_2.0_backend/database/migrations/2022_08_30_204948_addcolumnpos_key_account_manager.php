<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddcolumnposKeyAccountManager extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('cv_agent_mappings', 'pos_key_account_manager')) //check the column
        {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->string('pos_key_account_manager', 30)->nullable();
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
        if (Schema::hasColumn('cv_agent_mappings', 'pos_key_account_manager')) //check the column
        {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->dropColumn('pos_key_account_manager');
            });
        }
    }
}
