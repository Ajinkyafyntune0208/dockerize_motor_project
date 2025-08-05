<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLossOfPersonalBelongingSiValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('loss_of_personal_belonging_si_values', function (Blueprint $table) {
            $table->id();
            $table->string('option')->nullable();
            $table->integer('ic_id')->nullable();
            $table->string('ic_alias')->nullable();
            $table->enum('is_applicable',['Y','N'])->nullable()->default('Y');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('loss_of_personal_belonging_si_values');
    }
}
