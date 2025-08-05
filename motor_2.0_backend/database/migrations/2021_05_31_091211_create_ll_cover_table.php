<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLlCoverTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ll_cover', function (Blueprint $table) {
            $table->integer('ll_cover_id', true);
            $table->integer('ll_to_driver');
            $table->integer('ll_to_coolie');
            $table->integer('ll_to_cleaner');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ll_cover');
    }
}
