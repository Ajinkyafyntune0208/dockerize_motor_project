<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPreviousPolicyAddonsListTableUserProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('user_proposal'))
        {
            if (!Schema::hasColumn('user_proposal', 'previous_policy_addons_list'))
            {
                Schema::table('user_proposal', function (Blueprint $table) 
                {  
                    $table->text('previous_policy_addons_list')->nullable()->after('previous_policy_number');;
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
    }
}
