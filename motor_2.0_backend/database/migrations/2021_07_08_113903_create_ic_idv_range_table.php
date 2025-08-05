<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcIdvRangeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_idv_range', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('ic_id')->nullable();
            $table->float('min_idv', 10, 0)->nullable();
            $table->float('max_idv', 10, 0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ic_idv_range');
    }
}
