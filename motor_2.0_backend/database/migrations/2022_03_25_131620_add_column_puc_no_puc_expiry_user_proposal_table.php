<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPucNoPucExpiryUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('user_proposal', 'puc_no'))
        {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->text('puc_no')->nullable();
            });            
        } 
        if (!Schema::hasColumn('user_proposal', 'puc_expiry'))
        {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->text('puc_expiry')->nullable();
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
