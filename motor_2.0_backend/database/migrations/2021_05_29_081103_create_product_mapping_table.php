<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_mapping', function (Blueprint $table) {
            $table->integer('product_map_id', true);
            $table->integer('product_id');
            $table->integer('sub_product_id')->nullable();
            $table->integer('role_id')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->dateTime('created_date')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_mapping');
    }
}
