<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeProposalFields extends Migration
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
                $table->text('nominee_age')->nullable()->change();
                $table->text('car_registration_state')->nullable()->change();
                $table->text('car_registration_city')->nullable()->change();
                $table->text('nominee_name')->nullable()->change();
                
                /*
                -- Altering columns to be nullable and changing their type to TEXT
                    ALTER TABLE user_proposal 
                    MODIFY COLUMN nominee_age TEXT NULL,
                    MODIFY COLUMN car_registration_state TEXT NULL,
                    MODIFY COLUMN car_registration_city TEXT NULL,
                    MODIFY COLUMN nominee_name TEXT NULL;
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
