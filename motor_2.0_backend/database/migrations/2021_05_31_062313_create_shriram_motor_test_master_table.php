<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShriramMotorTestMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shriram_motor_test_master', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('manf', 50)->nullable();
            $table->string('model_desc', 150)->nullable();
            $table->string('veh_make', 150)->nullable();
            $table->string('veh_model', 150)->nullable();
            $table->string('veh_code', 150)->nullable();
            $table->string('veh_cc', 150)->nullable();
            $table->string('fuel', 150)->nullable();
            $table->string('veh_gvw', 150)->nullable();
            $table->string('veh_seat_cap', 150)->nullable();
            $table->string('ex_showroom', 150)->nullable();
            $table->string('body_desc', 150)->nullable();
            $table->string('veh_category', 150)->nullable();
            $table->string('veh_min_si', 150)->nullable();
            $table->string('veh_flex_09', 150)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shriram_motor_test_master');
    }
}
