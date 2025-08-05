<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeColumnTypesToLongText extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (app()->environment('local')) {
            Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
                $table->longText('request')->nullable()->change();
                $table->longText('response')->nullable()->change();
                $table->longText('headers')->nullable()->change();
            });

            /*
            
                -- Altering columns to be nullable and changing their type to LONGTEXT
                ALTER TABLE ckyc_logs_request_responses 
                MODIFY COLUMN request LONGTEXT NULL,
                MODIFY COLUMN response LONGTEXT NULL,
                MODIFY COLUMN headers LONGTEXT NULL;

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
        Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
            //
        });
    }
}
