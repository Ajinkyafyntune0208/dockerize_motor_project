<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunicationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_product_journey_id')->unsigned()->nullable();
            $table->enum('service_type', ['WHATSAPP', 'SMS', 'EMAIL', 'NA'])->default('NA');
            $table->text('request');
            $table->text('response');
            $table->enum('communication_module', ['NEW', 'RENEWAL', 'ROLLOVER', 'OTHER'])->default('OTHER');
            $table->enum('status', ['Y', 'N'])->default('N');
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
        Schema::dropIfExists('communication_logs');
    }
}
