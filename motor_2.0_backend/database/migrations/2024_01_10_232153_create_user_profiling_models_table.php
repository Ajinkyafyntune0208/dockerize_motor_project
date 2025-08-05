<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserProfilingModelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_profiling', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->longText('request')->nullable();
            $table->timestamps();
            $table->index('user_product_journey_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_profiling');
    }
}
