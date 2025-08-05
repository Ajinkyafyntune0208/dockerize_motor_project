<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAndIndexTableFastlaneRequestResponse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        if (Schema::hasTable('fastlane_request_response'))
        {

                Schema::table('fastlane_request_response', function (Blueprint $table) 
                {  
                    $table->string('transaction_type', 50)->nullable()->change();
                    $table->string('request', 20)->nullable()->change();
                    $table->string('endpoint_url', 255)->nullable()->change();
                    $table->string('ip_address', 20)->nullable()->change();
                    $table->string('section', 10)->nullable()->change();
                    $table->index([ 'transaction_type','request']);
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
        Schema::table('fastlane_request_response', function (Blueprint $table) 
        {  
            $table->dropIndex(['transaction_type','request']);
            // $table->text('transaction_type')->change();
            // $table->text('request')->change();
            $table->text('endpoint_url')->change();
            $table->text('ip_address')->change();
            $table->string('section',255)->nullable()->change();


        });
    }
}
