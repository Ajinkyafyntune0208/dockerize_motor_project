<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserProductMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_product_mapping', function (Blueprint $table) {
            $table->integer('user_map_id', true);
            $table->integer('user_id')->nullable();
            $table->integer('product_id')->nullable();
            $table->integer('sub_product_id')->nullable();
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
        Schema::dropIfExists('user_product_mapping');
    }
}
