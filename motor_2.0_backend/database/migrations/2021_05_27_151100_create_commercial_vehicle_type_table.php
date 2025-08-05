<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommercialVehicleTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commercial_vehicle_type', function (Blueprint $table) {
            $table->integer('com_vehicle_type_id', true);
            $table->string('com_vehicle_type_name', 50);
            $table->string('url', 500);
            $table->integer('product_sub_type_id');
            $table->string('status')->default('Y');
            $table->string('img', 100);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commercial_vehicle_type');
    }
}
