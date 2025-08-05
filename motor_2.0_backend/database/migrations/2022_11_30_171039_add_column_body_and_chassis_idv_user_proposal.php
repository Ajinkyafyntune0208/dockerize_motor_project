<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnBodyAndChassisIdvUserProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        if (Schema::hasTable('user_proposal'))
        {
            if (!Schema::hasColumn('user_proposal', 'body_idv') && !Schema::hasColumn('user_proposal', 'chassis_idv'))
            {
                Schema::table('user_proposal', function (Blueprint $table) 
                {  
                    $table->integer('body_idv')->nullable()->unsigned();
                    $table->integer('chassis_idv')->nullable()->unsigned();
                });
            }
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
        if (Schema::hasTable('user_proposal'))
        {
            if (Schema::hasColumn('user_proposal', 'body_idv') && Schema::hasColumn('user_proposal', 'chassis_idv'))
            {
                Schema::table('user_proposal', function (Blueprint $table) 
                {  
                    $table->dropColumn('body_idv');
                    $table->dropColumn('chassis_idv');
                });
            }
        }
    }

}
