<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMagmaBikePriceMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('magma_bike_price_masters', function (Blueprint $table) {
            $table->string('rto_location_name');
            $table->integer('vehicle_class_code');
            $table->string('vehicle_manufacturer_code');
            $table->string('vehicle_model_code');
            $table->integer('vehicle_selling_price');
            $table->integer('num_pg_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('magma_bike_price_masters');
    }
}
