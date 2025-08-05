<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGcvTppdTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gcv_tppd', function (Blueprint $table) {
            $table->increments('tppd_id');
            $table->date('effective_todate');
            $table->date('effective_fromdate');
            $table->unsignedInteger('weight_min');
            $table->unsignedInteger('weight_max');
            $table->unsignedInteger('premium_amount');
            $table->integer('product_sub_type_id')->default(0);
            $table->integer('vehicle_type')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gcv_tppd');
    }
}
