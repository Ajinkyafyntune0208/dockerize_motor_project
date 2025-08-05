<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsFinsallAvailableInUserProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->enum('is_finsall_available',['Y','N'])->nullable()->default('N');
            });   
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            if (Schema::hasColumn('user_proposal', 'is_finsall_available'))
            {
                $table->dropColumn('is_finsall_available');
            }
        });
    }
}
