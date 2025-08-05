<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorTppdTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor_tppd', function (Blueprint $table) {
            $table->increments('tppd_id');
            $table->dateTime('effective_todate')->nullable();
            $table->dateTime('effective_fromdate')->nullable();
            $table->unsignedInteger('cc_min');
            $table->unsignedInteger('cc_max');
            $table->unsignedInteger('premium_amount');
            $table->integer('product_sub_type_id')->default(0);
            $table->integer('applicable_year')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_tppd');
    }
}
