<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableKafkaPushData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kafka_data_push_logs', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->bigInteger('user_product_journey_id');
            $table->string('stage', 50)->nullable();
            $table->text('request')->nullable();
            $table->string('source', 50)->nullable();
            $table->dateTime('created_on')->nullable();
            $table->index(['stage', 'user_product_journey_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kafka_data_push_logs');
    }
}
