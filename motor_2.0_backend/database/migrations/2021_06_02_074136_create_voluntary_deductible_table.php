<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVoluntaryDeductibleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voluntary_deductible', function (Blueprint $table) {
            $table->integer('voluntary_id', true);
            $table->integer('product_id')->nullable();
            $table->integer('deductible_amount')->nullable();
            $table->integer('discount_in_percent')->nullable();
            $table->integer('max_amount')->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_to')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voluntary_deductible');
    }
}
