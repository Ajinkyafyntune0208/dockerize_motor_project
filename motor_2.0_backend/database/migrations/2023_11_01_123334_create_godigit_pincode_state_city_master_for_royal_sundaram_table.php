<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class CreateGodigitPincodeStateCityMasterForRoyalSundaramTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('godigit_pincode_state_city_master_for_royal_sundaram');
        Schema::create('godigit_pincode_state_city_master_for_royal_sundaram', function (Blueprint $table) {
            $table->id();
            $table->integer('pincode')->nullable();
            $table->string('state', 100)->nullable();
            $table->smallInteger('statecode')->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 10)->nullable();          
        });
        Artisan::call('db:seed --class=godigit_pincode_state_city_master_for_royal_sundaram');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('godigit_pincode_state_city_master_for_royal_sundaram');
    }
}
