<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitedIndiaRtoCityDiscount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('united_india_rto_city_discount', function (Blueprint $table) {
            $table->string('rto_id');
            $table->string('rto_group_id');
            $table->string('state_id');
            $table->string('zone_id');
            $table->string('rto_code');
            $table->string('rto_number');
            $table->string('rto_name');
            $table->string('discount_grid_rto_city');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('united_india_rto_city_discount');
    }
}
