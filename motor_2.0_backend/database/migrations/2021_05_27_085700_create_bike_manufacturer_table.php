<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBikeManufacturerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bike_manufacturer', function (Blueprint $table) {
            $table->increments('manf_id');
            $table->string('manf_name', 200)->default('');
            $table->boolean('is_discontinued')->default(0)->comment('1:True,0:False');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bike_manufacturer');
    }
}
