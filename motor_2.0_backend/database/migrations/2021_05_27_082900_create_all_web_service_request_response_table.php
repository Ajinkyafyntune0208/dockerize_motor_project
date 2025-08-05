<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAllWebServiceRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('all_web_service_request_response', function (Blueprint $table) {
            $table->integer('request_response_id', true);
            $table->integer('product_sub_type_id')->nullable();
            $table->string('method', 100);
            $table->string('service_url', 5000);
            $table->longText('service_request');
            $table->longText('service_response');
            $table->integer('ic_id');
            $table->string('curl_info', 8000)->nullable();
            $table->dateTime('created_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('all_web_service_request_response');
    }
}
