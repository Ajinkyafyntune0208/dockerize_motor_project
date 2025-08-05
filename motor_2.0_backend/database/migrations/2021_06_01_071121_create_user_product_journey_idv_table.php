<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserProductJourneyIdvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_product_journey_idv', function (Blueprint $table) {
            $table->integer('user_product_journey_idv_id', true);
            $table->integer('user_product_journey_id');
            $table->integer('ic_id');
            $table->string('calculated_idv', 20);
            $table->integer('min_idv');
            $table->integer('max_idv');
            $table->dateTime('created_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_product_journey_idv');
    }
}
