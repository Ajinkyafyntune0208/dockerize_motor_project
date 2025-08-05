<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Database\Seeders\UniversalSompoCarAddonConfiguration;

class AddedTableUniversalSompoCarAddonConfiguration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('universal_sompo_car_addon_configuration')) 
        {
            Schema::create('universal_sompo_car_addon_configuration', function (Blueprint $table) {
                $table->id();
                $table->text('vehicle_make')->nullable();
                $table->text('age_range')->nullable();
                $table->text('zero_dep')->nullable();
                $table->text('engine_protector')->nullable();
                $table->text('return_to_invoice')->nullable();
                $table->text('road_side_assistance')->nullable();
                $table->text('key_replacement')->nullable();
                $table->text('consumable')->nullable();
                $table->text('tyre_secure')->nullable();
                $table->text('loss_of_belongings')->nullable();
            });    
        }
        if (Schema::hasTable('universal_sompo_car_addon_configuration')) 
        {
            $seeder = new UniversalSompoCarAddonConfiguration();
            $seeder->run();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('universal_sompo_car_addon_configuration');
    }
}
