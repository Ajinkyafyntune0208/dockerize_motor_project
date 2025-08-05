<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHdfcErgoMotorPincodeMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hdfc_ergo_motor_pincode_master', function (Blueprint $table) {
            $table->integer('num_state_cd')->nullable();
            $table->integer('num_citydistrict_cd')->nullable();
            $table->integer('num_pincode')->nullable();
            $table->text('txt_pincode_locality')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hdfc_ergo_motor_pincode_master');
    }
}
