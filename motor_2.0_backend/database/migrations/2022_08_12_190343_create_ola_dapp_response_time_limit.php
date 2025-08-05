<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOlaDappResponseTimeLimit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ola_dapp_response_time_limit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_product_journey_id')->nullable();
            $table->string('rc_number')->nullable();
            $table->string('ongrid_response_status')->nullable();
            $table->string('ongrid_response_time')->nullable();
            $table->string('dapp_response_time')->nullable();
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
        Schema::dropIfExists('ola_dapp_response_time_limit');
    }
}
