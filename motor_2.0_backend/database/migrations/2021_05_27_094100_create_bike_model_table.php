<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBikeModelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bike_model', function (Blueprint $table) {
            $table->increments('model_id');
            $table->unsignedInteger('manf_id')->index('FK_MI_Model_MI_ManufacturerMaster');
            $table->string('model_name', 200)->default('');
            $table->boolean('is_discontinued')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bike_model');
    }
}
