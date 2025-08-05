<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class CreateRoyalSundaramRtoPincodeMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('royal_sundaram_rto_pincode_master');
        Schema::create('royal_sundaram_rto_pincode_master', function (Blueprint $table) {
            $table->id();
            $table->string('RTO_NUMBER',100)->nullable();
            $table->string('RTO_NAME',100)->nullable();
            $table->smallInteger('DIGIT_STATE_CODE')->nullable();
            $table->string('CITY_CODE',100)->nullable();
            $table->string('CITY_NAME',100)->nullable();
            $table->string('STATE_CODE',100)->nullable();
            $table->string('STATE_NAME',100)->nullable();
            $table->string('REGION',100)->nullable();
            $table->string('ZONE',100)->nullable();
        });
        Artisan::call('db:seed --class=royal_sundaram_rto_pincode_master');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('royal_sundaram_rto_pincode_master');
    }
}
