<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbiblDailerAttemptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('abibl_dailer_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_product_journey_id')->nullable();
            $table->unsignedInteger('attempts')->nullable();
            $table->date('next_attempts_on')->nullable();
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
        Schema::dropIfExists('abibl_dailer_attemps');
    }
}
