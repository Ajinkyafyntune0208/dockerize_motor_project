<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCkycMetaData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (app()->environment('local')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->text('ckyc_meta_data')->nullable()->change();
            });

            /*
                -- Altering the column to be nullable and changing its type to TEXT
                ALTER TABLE user_proposal 
                MODIFY COLUMN ckyc_meta_data TEXT NULL;
             */
        }
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
