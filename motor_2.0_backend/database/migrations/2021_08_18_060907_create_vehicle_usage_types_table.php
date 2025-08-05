<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleUsageTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_usage_types', function (Blueprint $table) {
            $table->id();
            $table->integer('vehicle_usage_type_id')->nullable();
            $table->integer('vehicle_category_id')->nullable();
            $table->string('vehicle_usage_type', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_usage_types');
    }
}
