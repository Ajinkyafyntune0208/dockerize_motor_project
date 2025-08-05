<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKotakPincodeMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kotak_pincode_master', function (Blueprint $table) {
            $table->string('NUM_STATE_CD', 50)->nullable();
            $table->string('NUM_CITYDISTRICT_CD', 50)->nullable();
            $table->string('NUM_PINCODE', 50)->nullable();
            $table->string('TXT_PINCODE_LOCALITY', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kotak_pincode_master');
    }
}
