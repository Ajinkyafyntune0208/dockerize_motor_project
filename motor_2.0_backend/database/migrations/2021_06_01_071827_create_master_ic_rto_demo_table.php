<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterIcRtoDemoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_ic_rto_demo', function (Blueprint $table) {
            $table->integer('ic_rto_id');
            $table->integer('rto_id')->nullable();
            $table->integer('range_id')->nullable()->unique('range_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_ic_rto_demo');
    }
}
