<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMiscIciciLombardRtoMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('misc_icici_lombard_rto_master', function (Blueprint $table) {
          
            
            $table->string('CountryCode', true);
            $table->string('ILStateCode', true);
            $table->string('ILState', true);
            $table->string('GSTStateID', true);
            $table->string('CityDistrictCode', true);
            $table->string('RTOLocationCode', true);
            $table->string('RTOLocationDesciption', true);
            $table->string('VehicleClassCode', true);
            $table->string('Status', true);
            $table->string('ActiveFlag', true);

            $table->string('Vehicle Subclass', true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('misc_icici_lombard_rto_master');
    }
}
