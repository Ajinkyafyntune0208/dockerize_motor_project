<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRenewalDataApiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renewal_data_api', function (Blueprint $table) {
            $table->id();
            $table->integer('user_product_journey_id');
            $table->string('registration_no')->nullable();
            $table->string('policy_number')->nullable();
            $table->text('api_request');
            $table->text('api_response');
            $table->text('required_data')->nullable();
            $table->text('url');
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
        Schema::dropIfExists('renewal_data_api');
    }
}
