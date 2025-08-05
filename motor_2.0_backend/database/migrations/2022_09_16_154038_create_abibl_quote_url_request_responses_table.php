<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbiblQuoteUrlrequestResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('abibl_quote_url_request_responses', function (Blueprint $table) {
            $table->id();
            $table->string('registration_no')->nullable()->index();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->string('response_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('abibl_quote_url_request_responses');
    }
}
