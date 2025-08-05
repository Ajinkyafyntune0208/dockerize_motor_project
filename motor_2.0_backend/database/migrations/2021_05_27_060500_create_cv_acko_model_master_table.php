<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvAckoModelMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_acko_model_master', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('make', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->string('version', 100)->nullable();
            $table->integer('version_id')->nullable();
            $table->string('cubic_capacity', 50)->nullable();
            $table->string('seating_capacity', 50)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('transmission_type', 50)->nullable();
            $table->boolean('is_acrtive')->default(0);
            $table->dateTime('createdon')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cv_acko_model_master');
    }
}
