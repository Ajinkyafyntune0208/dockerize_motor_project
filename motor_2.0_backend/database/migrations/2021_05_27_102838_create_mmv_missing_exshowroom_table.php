<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMmvMissingExshowroomTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mmv_missing_exshowroom', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->string('manf_name', 50)->nullable();
            $table->string('model_name', 150)->nullable();
            $table->string('version_name', 150)->nullable();
            $table->integer('version_id')->nullable();
            $table->integer('cubic_capacity')->nullable();
            $table->integer('carrying_capicity')->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->integer('showroom_price')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mmv_missing_exshowroom');
    }
}
