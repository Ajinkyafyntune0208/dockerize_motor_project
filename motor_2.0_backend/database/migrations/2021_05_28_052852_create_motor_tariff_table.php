<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorTariffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor_tariff', function (Blueprint $table) {
            $table->increments('tariff_id');
            $table->unsignedInteger('zone_id');
            $table->unsignedInteger('cc_min');
            $table->unsignedInteger('cc_max');
            $table->unsignedInteger('age_min');
            $table->unsignedInteger('age_max');
            $table->decimal('rate', 10, 4)->nullable();
            $table->integer('product_sub_type_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_tariff');
    }
}
