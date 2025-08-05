<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductRtoZoneTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_rto_zone', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('product_sub_type_id');
            $table->integer('zone_id');
            $table->integer('rto_id');
            $table->string('rto_number', 50);
            $table->string('status')->default('Y');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_rto_zone');
    }
}
