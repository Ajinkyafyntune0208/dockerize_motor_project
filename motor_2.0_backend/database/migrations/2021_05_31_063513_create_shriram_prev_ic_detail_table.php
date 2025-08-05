<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShriramPrevIcDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shriram_prev_ic_detail', function (Blueprint $table) {
            $table->integer('shriram_prev_ic_detail_id', true);
            $table->string('pc_type', 100)->nullable();
            $table->string('pc_desc', 100)->nullable();
            $table->integer('product_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shriram_prev_ic_detail');
    }
}
