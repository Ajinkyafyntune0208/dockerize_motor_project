<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class CreateUniversalSompoBikeAddonConfigurationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('universal_sompo_bike_addon_configuration', function (Blueprint $table) {
            $table->id();
            $table->text('vehicle_make')->nullable();
            $table->text('age_range')->nullable();
            $table->text('zero_dep')->nullable();
            $table->text('engine_protector')->nullable();
            $table->text('return_to_invoice')->nullable();
            $table->text('road_side_assistance')->nullable();
            $table->text('consumable')->nullable();
        });
        
        Artisan::call('db:seed --class=UniversalSompoBikeAddonConfigurationSeeder');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('universal_sompo_bike_addon_configuration');
    }
}
