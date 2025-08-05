<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeProposalFieldsToText extends Migration
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
                $table->dropIndex(['mobile_number']);
                $table->dropIndex(['email']);
            });
            
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->text('pincode')->nullable()->change();
                $table->text('car_registration_city_id')->nullable()->change();
                $table->text('car_registration_state_id')->nullable()->change();
                $table->text('pan_number')->nullable()->change();
                $table->text('gst_number')->nullable()->change();
                $table->text('dob')->nullable()->change();
                $table->text('email')->nullable()->change();
                $table->text('first_name')->nullable()->change();
                $table->text('gender')->nullable()->change();
                $table->text('gender_name')->nullable()->change();
                $table->text('last_name')->nullable()->change();
                $table->text('marital_status')->nullable()->change();
                $table->text('hypothecation_city')->nullable()->change();
                $table->text('nominee_dob')->nullable()->change();
                $table->text('occupation')->nullable()->change();
                $table->text('occupation_name')->nullable()->change();
                $table->text('mobile_number')->nullable()->change();
            });


            /*
                -- Dropping indexes
                DROP INDEX user_proposal_mobile_number_index ON user_proposal;
                DROP INDEX user_proposal_email_index ON user_proposal;

                -- Altering columns to be nullable and changing their type to TEXT
                ALTER TABLE user_proposal 
                MODIFY COLUMN pincode TEXT NULL,
                MODIFY COLUMN car_registration_city_id TEXT NULL,
                MODIFY COLUMN car_registration_state_id TEXT NULL,
                MODIFY COLUMN pan_number TEXT NULL,
                MODIFY COLUMN gst_number TEXT NULL,
                MODIFY COLUMN dob TEXT NULL,
                MODIFY COLUMN email TEXT NULL,
                MODIFY COLUMN first_name TEXT NULL,
                MODIFY COLUMN gender TEXT NULL,
                MODIFY COLUMN gender_name TEXT NULL,
                MODIFY COLUMN last_name TEXT NULL,
                MODIFY COLUMN marital_status TEXT NULL,
                MODIFY COLUMN hypothecation_city TEXT NULL,
                MODIFY COLUMN nominee_dob TEXT NULL,
                MODIFY COLUMN occupation TEXT NULL,
                MODIFY COLUMN occupation_name TEXT NULL,
                MODIFY COLUMN mobile_number TEXT NULL;

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
        if (app()->environment('local')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->integer('pincode')->nullable()->change();
                $table->string('car_registration_city_id', 255)->nullable()->change();
                $table->string('car_registration_state_id', 255)->nullable()->change();
                $table->string('pan_number', 50)->nullable()->change();
                $table->string('gst_number', 50)->nullable()->change();
                $table->string('dob', 50)->nullable()->change();
                $table->string('email', 255)->nullable()->change();
                $table->string('first_name', 255)->nullable()->change();
                $table->string('gender', 50)->nullable()->change();
                $table->string('gender_name', 255)->nullable()->change();
                $table->string('last_name', 255)->nullable()->change();
                $table->string('mobile_number', 15)->nullable()->change();
                $table->string('hypothecation_city', 100)->nullable()->change();
                $table->string('nominee_dob', 255)->nullable()->change();
                $table->string('occupation', 50)->nullable()->change();
                $table->string('occupation_name', 255)->nullable()->change();
                $table->string('marital_status', 255)->nullable()->change();
    
            });
        }
    }
}
