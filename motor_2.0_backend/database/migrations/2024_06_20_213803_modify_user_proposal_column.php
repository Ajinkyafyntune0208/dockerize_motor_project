<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyUserProposalColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            if (app()->environment('local')) {
                $table->text('nominee_relationship')->nullable()->change();
                
                /*
                -- Altering columns to be nullable and changing their type to TEXT
                    ALTER TABLE user_proposal 
                    MODIFY COLUMN nominee_relationship TEXT NULL
                */
            }
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
            //
        });
    }
}
