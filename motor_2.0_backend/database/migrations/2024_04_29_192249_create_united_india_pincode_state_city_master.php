<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitedIndiaPincodeStateCityMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        if (!(Schema::hasTable('united_india_pincode_state_city_master'))) {
            Schema::create('united_india_pincode_state_city_master', function (Blueprint $table) {
                $table->string('TXT_STATE');
                $table->string('TXT_CITYDISTRICT');
                $table->integer('NUM_PINCODE');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('united_india_pincode_state_city_master');
    }
}
