<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitedIndiaOtherModelsDiscountNewGrid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('united_india_other_models_discount_new_grid', function (Blueprint $table) {
            $table->string('brand');
            $table->string('model');
            $table->string('newbusiness');
            $table->string('renewal');
            $table->string('rollover');
            $table->string('package');
            $table->string('own_damage');
            $table->string('own_damage_without_ncb');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('united_india_other_models_discount_grid');
    }
}
