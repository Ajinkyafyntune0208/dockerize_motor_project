<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDigitGcvMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('digit_gcv_master', function (Blueprint $table) {
            $table->integer('digit_gcv_id', true);
            $table->string('vehicle_code', 50)->nullable();
            $table->string('make', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->string('version', 50)->nullable();
            $table->string('bodytype', 50)->nullable();
            $table->integer('seatingcapacity')->nullable();
            $table->decimal('power', 10, 0)->nullable();
            $table->integer('cubic_capacity')->nullable();
            $table->integer('grosss_vehicle_weight')->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->integer('no_of_wheels')->nullable();
            $table->double('length')->nullable();
            $table->double('ex_showroom')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('digit_gcv_master');
    }
}
