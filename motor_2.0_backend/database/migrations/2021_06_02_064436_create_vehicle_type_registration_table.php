<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleTypeRegistrationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_type_registration', function (Blueprint $table) {
            $table->integer('vehicle_type_id', true);
            $table->string('vehicle_type', 50);
            $table->string('status')->default('Active');
            $table->integer('created_by');
            $table->dateTime('created_date')->useCurrent();
            $table->integer('updated_by');
            $table->dateTime('updated_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_type_registration');
    }
}
