<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductGstMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_gst_mapping', function (Blueprint $table) {
            $table->integer('product_gst_mapping_id', true);
            $table->integer('product_sub_type_id')->nullable();
            $table->integer('payout_head_id')->nullable();
            $table->integer('gst')->nullable();
            $table->dateTime('effective_from')->nullable()->useCurrent();
            $table->dateTime('effective_to')->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->dateTime('createdon')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_gst_mapping');
    }
}
