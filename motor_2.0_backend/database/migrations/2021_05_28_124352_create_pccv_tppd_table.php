<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePccvTppdTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pccv_tppd', function (Blueprint $table) {
            $table->increments('tppd_id');
            $table->date('effective_fromdate');
            $table->date('effective_todate');
            $table->integer('cc_min');
            $table->integer('cc_max');
            $table->unsignedInteger('fixed_amount');
            $table->unsignedInteger('additional_amount');
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
        Schema::dropIfExists('pccv_tppd');
    }
}
