<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOlaAppApiRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ola_app_api_request_response', function (Blueprint $table) {
            $table->integer('web_service_request_response_id', true);
            $table->longText('service_data_request')->nullable();
            $table->longText('service_data_response')->nullable();
            $table->longText('service_for')->nullable();
            $table->dateTime('created_date')->nullable()->useCurrent();
            $table->dateTime('updated_date')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ola_app_api_request_response');
    }
}
