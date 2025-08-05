<?php

use Database\Seeders\FastlaneVehicleDescriptionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFastlaneVehicleDescription extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fastlane_vehicle_description', function (Blueprint $table) {
            $table->id();
            $table->string('section')->nullable();
            $table->string('description')->nullable();
        });
        $seeder = new FastlaneVehicleDescriptionSeeder();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fastlane_vehicle_description');
    }
}
