<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserJourneyKafkaPaymentStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_journey_kafka_payment_status', function (Blueprint $table) {
            $table->id();
            $table->integer('user_product_journey_id');
            $table->string('stage', 20)->nullable();
            $table->timestamps();
            $table->index('user_product_journey_id');
            $table->index('stage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_journey_kafka_payment_status');
    }
}
