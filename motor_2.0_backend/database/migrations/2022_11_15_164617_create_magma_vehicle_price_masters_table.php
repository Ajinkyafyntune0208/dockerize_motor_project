<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMagmaVehiclePriceMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('magma_vehicle_price_master', function (Blueprint $table) {
            $table->string('rtolocationcode');
            $table->integer('vehicleclasscode');
            $table->string('vehiclemanufacturercode');
            $table->string('vehiclemodelcode');
            $table->integer('vehiclesellingprice');
            $table->integer('num_pg_id');
            $table->string('vehiclemodelstatus');
            $table->string('dat_start_date');
            $table->string('dat_end_date');
            $table->string('txt_active_flag');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('magma_vehicle_price_masters');
    }
}
