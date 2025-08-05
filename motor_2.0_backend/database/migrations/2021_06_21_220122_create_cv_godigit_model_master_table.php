<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvGodigitModelMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_godigit_model_master', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vehicle_code');
            $table->string('make',200)->nullable();
            $table->string('model',200)->nullable();
            $table->string('variant',200)->nullable();
            $table->string('body_type',200)->nullable();
            $table->integer('seating_capacity')->nullable();
            $table->integer('power')->nullable();
            $table->integer('cubic_capacity')->nullable();
            $table->integer('gross_vehicle_weight')->nullable();
            $table->string('fuel_type',50)->nullable();
            $table->integer('no_of_wheels')->nullable();
            $table->string('abs',10)->nullable();
            $table->bigInteger('air_bags')->nullable();
            $table->float('length')->nullable();
            $table->bigInteger('ex_showroom_price')->nullable();
            $table->string('price_year',50)->nullable();
            $table->string('production',100)->nullable();
            $table->string('manufacturing',100)->nullable();
            $table->string('vehicle_type',200)->nullable();
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
        Schema::dropIfExists('cv_godigit_model_master');
    }
}
