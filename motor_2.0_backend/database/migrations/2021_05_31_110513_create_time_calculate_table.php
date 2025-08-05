<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTimeCalculateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('time_calculate', function (Blueprint $table) {
            $table->bigInteger('time_calculate_id', true);
            $table->dateTime('execute_current_time')->useCurrent();
            $table->integer('user_product_journey_id');
            $table->string('from_where', 250);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('time_calculate');
    }
}
