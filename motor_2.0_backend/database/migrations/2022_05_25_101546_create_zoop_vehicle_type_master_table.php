<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZoopVehicleTypeMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('zoop_vehicle_type_master', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->string('rc_vh_class_desc')->nullable();
            $table->string('rc_vch_catg')->nullable();
            $table->integer('ft_sub_type_id')->nullable();
            $table->string('ft_sub_type_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zoop_vehicle_type_master');
    }
}
