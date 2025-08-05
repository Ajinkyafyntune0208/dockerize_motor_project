<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHdfcErgoMotorCityMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hdfc_ergo_motor_city_master', function (Blueprint $table) {
            $table->integer('num_state_cd')->unsigned();
            $table->integer('num_citydistrict_cd')->unsigned();
            $table->string('txt_citydistrict', 255);
            $table->string('txt_city_district_flag', 255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hdfc_ergo_motor_city_master');
    }
}
