<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGcvFyntuneMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gcv_fyntune_master', function (Blueprint $table) {
            $table->integer('master_id', true);
            $table->integer('manf_id')->nullable();
            $table->string('make_name', 50)->nullable();
            $table->string('model_name', 50)->nullable();
            $table->string('version_name', 50)->nullable();
            $table->string('cubic_capacity', 50)->nullable();
            $table->string('is_discontinued', 50)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('seating_capacity', 50)->nullable();
            $table->string('gross_weight', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gcv_fyntune_master');
    }
}
