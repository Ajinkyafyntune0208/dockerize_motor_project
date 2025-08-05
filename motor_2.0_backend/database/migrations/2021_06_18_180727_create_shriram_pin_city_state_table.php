<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShriramPinCityStateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shriram_pin_city_state', function (Blueprint $table) {
            $table->integer('pin_code')->nullable();
            $table->text('pin_desc')->nullable();
            $table->text('pc_short_desc')->nullable();
            $table->text('city')->nullable();
            $table->text('state')->nullable();
            $table->text('state_desc')->nullable();
        });
    }
   
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shriram_pin_city_state');
    }
}
