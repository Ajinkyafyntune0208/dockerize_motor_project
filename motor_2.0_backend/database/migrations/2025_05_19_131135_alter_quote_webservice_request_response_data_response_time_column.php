<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterQuoteWebserviceRequestResponseDataResponseTimeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        // Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
        //     $table->integer('response_time')->default(0)->change();
        // });

        // Schema::table('webservice_request_response_data', function (Blueprint $table) {
        //     $table->integer('response_time')->default(0)->change();
        // });
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
