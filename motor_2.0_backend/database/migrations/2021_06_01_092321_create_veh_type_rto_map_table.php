<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehTypeRtoMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('veh_type_rto_map', function (Blueprint $table) {
            $table->integer('veh_type_rto_map_id', true);
            $table->integer('vehicle_type_id');
            $table->integer('rto_code_id');
            $table->string('zone_name', 50);
            $table->string('status')->default('Active');
            $table->integer('created_by');
            $table->dateTime('created_date');
            $table->integer('updated_by');
            $table->dateTime('updated_date');
            $table->integer('deleted_by');
            $table->dateTime('deleted_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('veh_type_rto_map');
    }
}
