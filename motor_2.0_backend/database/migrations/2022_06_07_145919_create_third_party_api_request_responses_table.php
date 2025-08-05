<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThirdPartyApiRequestResponsesTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * @return void
     */
    public function up()
    {
        Schema::create('third_party_api_request_responses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('url')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->json('headers')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('options')->nullable();
            $table->string('response_time')->nullable();
            $table->string('http_status')->nullable();
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
        Schema::dropIfExists('third_party_api_request_responses');
    }
}
