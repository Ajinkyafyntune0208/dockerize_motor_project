<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciMmvMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_mmv_master', function (Blueprint $table) {
            $table->double('manf_code')->nullable();
            $table->string('manf_name', 100)->nullable();
            $table->double('model_code')->nullable();
            $table->string('model_name', 100)->nullable();
            $table->double('no_of_wheels')->nullable();
            $table->double('cubic_capacity')->nullable();
            $table->double('seating_capacity')->nullable();
            $table->double('carrying_capacity')->nullable();
            $table->string('fuel_type', 100)->nullable();
            $table->double('num_vehicle_sub_class_code')->nullable();
            $table->string('num_vehicle_sub_class_desc', 100)->nullable();
            $table->string('car_sagment', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_mmv_master');
    }
}
