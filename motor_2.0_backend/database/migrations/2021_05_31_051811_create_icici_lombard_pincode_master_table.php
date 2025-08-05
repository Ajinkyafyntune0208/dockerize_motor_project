<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciLombardPincodeMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_lombard_pincode_master', function (Blueprint $table) {
            $table->string('il_state_id', 50)->nullable();
            $table->string('il_citydistrict_id', 50)->nullable();
            $table->string('num_pincode', 50)->nullable();
            $table->string('il_pincode_locality', 50)->nullable();
            $table->string('il_country_id', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_lombard_pincode_master');
    }
}
