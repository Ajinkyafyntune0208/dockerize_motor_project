<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterPincodeStateCityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_pincode_state_city', function (Blueprint $table) {
            $table->integer('master_pincode_id', true);
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
        Schema::dropIfExists('master_pincode_state_city');
    }
}
