<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOdDiscountMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('od_discount_master', function (Blueprint $table) {
            $table->integer('od_discount_id', true);
            $table->integer('manufacture')->nullable();
            $table->integer('model')->nullable();
            $table->integer('version')->nullable();
            $table->integer('rto')->nullable();
            $table->integer('age')->nullable();
            $table->decimal('discount', 10, 0)->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_to')->nullable();
            $table->integer('city')->nullable();
            $table->integer('state')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('od_discount_master');
    }
}
