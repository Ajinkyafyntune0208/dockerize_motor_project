<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReliancePincodeStateCityMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reliance_pincode_state_city_master', function (Blueprint $table) {
            $table->integer('state_id_pk');
            $table->text('state_name');
            $table->integer('district_id_pk');
            $table->text('district_name');
            $table->integer('city_or_village_id_pk');
            $table->text('city_or_village_name');
            $table->integer('area_id_pk');
            $table->text('area_name');
            $table->integer('pincode');
        });
    }
   
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reliance_pincode_state_city_master');
    }
}
