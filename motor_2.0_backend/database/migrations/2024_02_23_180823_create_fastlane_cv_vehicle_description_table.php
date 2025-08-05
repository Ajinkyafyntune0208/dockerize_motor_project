<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFastlaneCvVehicleDescriptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fastlane_cv_vehicle_description', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_class',25)->nullable();
            $table->string('vehicle_category',25)->nullable();
            $table->string('cv_section',25)->nullable();
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
        Schema::dropIfExists('fastlane_cv_vehicle_description');
    }
}
