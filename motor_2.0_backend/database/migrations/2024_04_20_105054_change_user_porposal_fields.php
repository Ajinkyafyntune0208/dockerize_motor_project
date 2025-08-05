<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeUserPorposalFields extends Migration
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
                $table->text('office_email')->nullable()->change();

                /*
                -- Altering the column to be nullable and changing its type to TEXT
                    ALTER TABLE user_proposal 
                    MODIFY COLUMN office_email TEXT NULL;
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
        if (app()->environment('local')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->string('office_email', 255)->nullable()->change();
            });
        }
    }
}
