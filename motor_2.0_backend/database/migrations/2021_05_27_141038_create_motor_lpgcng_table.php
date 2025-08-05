<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorLpgcngTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor_lpgcng', function (Blueprint $table) {
            $table->increments('lpgcng_id');
            $table->decimal('lpgcng_kit', 18, 4);
            $table->decimal('lpgcng_inbuild', 18, 4);
            $table->decimal('cpgcng_tp', 18, 4);
            $table->decimal('electrical', 18, 4);
            $table->decimal('nonelectrical', 18, 4);
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_lpgcng');
    }
}
