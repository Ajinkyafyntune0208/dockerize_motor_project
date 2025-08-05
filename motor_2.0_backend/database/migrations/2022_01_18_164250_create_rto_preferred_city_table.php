<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRtoPreferredCityTable extends Migration
{
    public function up()
    {
        Schema::create('rto_preferred_city', function (Blueprint $table) {
    		$table->id('preferred_city_id');
    		$table->string('city_name')->nullable();
    		$table->unsignedBigInteger('priority',)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rto_preferred_city');
    }
}