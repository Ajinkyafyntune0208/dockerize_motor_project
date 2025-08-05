<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterStateDistCityMapIcTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_state_dist_city_map_ic', function (Blueprint $table) {
            $table->integer('state_dist_city_map_ic_id', true);
            $table->string('ic_id', 50);
            $table->integer('state_id');
            $table->string('state_name', 50);
            $table->integer('district_id');
            $table->string('district_name', 50);
            $table->integer('city_id');
            $table->string('city_name', 50);
            $table->integer('area_id');
            $table->string('area_name', 50);
            $table->integer('pincode')->nullable();
            $table->integer('partner_city_id')->nullable()->comment('fyntune city id');
            $table->integer('partner_area_id')->nullable();
            $table->integer('partner_pincode');
            $table->string('status')->default('Active');
            $table->string('created_by', 50);
            $table->dateTime('created_date');
            $table->string('updated_by', 50);
            $table->dateTime('updated_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_state_dist_city_map_ic');
    }
}
