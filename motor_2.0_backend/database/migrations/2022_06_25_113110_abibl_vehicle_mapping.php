<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AbiblVehicleMapping extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('abibl_vehicle_mapping')) 
        {
            Schema::create('abibl_vehicle_mapping', function (Blueprint $table) 
            {
                $table->id();
                $table->string('FTS_CODE')->nullable();
                $table->string('variant_code')->nullable();
                $table->string('manf')->nullable();
                $table->string('model_name')->nullable();
                $table->string('variant_name')->nullable();
                $table->string('cc')->nullable();
                $table->string('sc')->nullable();
                $table->string('fuel_type')->nullable();            
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
        //
    }
}
