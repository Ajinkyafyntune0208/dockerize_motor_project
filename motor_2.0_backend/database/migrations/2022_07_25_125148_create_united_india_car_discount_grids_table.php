<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitedIndiaCarDiscountGridsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('united_india_car_discount_grid', function (Blueprint $table) {
            $table->integer('num_special_discount_rate')->nullable();
            $table->string('unique_id')->nullable();
            $table->string('ibb_code')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('variant')->nullable();
            $table->integer('iib_tac_code');
            $table->string('fuel_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('united_india_car_discount_grid');
    }
}