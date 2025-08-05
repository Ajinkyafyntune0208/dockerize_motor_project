<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciLombardStateMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_lombard_state_master', function (Blueprint $table) {
            $table->id();
            $table->integer('il_state_id')->nullable();
            $table->string('il_state', 50)->nullable();
            $table->integer('num_country_id')->nullable();
            $table->integer('num_gst_state_cd')->nullable();
            $table->string('gst_state', 50)->nullable();
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
        Schema::dropIfExists('icici_lombard_state_master');
    }
}
