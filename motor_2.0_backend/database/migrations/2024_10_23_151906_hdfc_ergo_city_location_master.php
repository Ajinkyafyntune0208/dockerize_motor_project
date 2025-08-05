<?php

use Database\Seeders\HdfcErgoCityLocationMasterSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HdfcErgoCityLocationMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('hdfc_ergo_city_location_master')) {
            Schema::create('hdfc_ergo_city_location_master', function (Blueprint $table) {
                $table->id();
                $table->string('city_id');
                $table->string('city');
                $table->string('location_id');
                $table->string('location');
                $table->timestamps();
            });
            $seeder = new HdfcErgoCityLocationMasterSeeder ();
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
        Schema::dropIfExists('hdfc_ergo_city_location_master');
    }
}
