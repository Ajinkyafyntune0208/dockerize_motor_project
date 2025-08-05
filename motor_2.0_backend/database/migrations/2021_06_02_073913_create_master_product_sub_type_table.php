<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterProductSubTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_product_sub_type', function (Blueprint $table) {
            $table->integer('product_sub_type_id', true);
            $table->integer('product_id');
            $table->integer('corp_client_id');
            $table->integer('insu_id');
            $table->integer('parent_id');
            $table->string('product_sub_type_code', 50);
            $table->string('product_sub_type_name');
            $table->string('short_name');
            $table->string('logo');
            $table->enum('status',['Active','Inactive'])->nullable();
            $table->dateTime('created_at');
            $table->dateTime('update_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_product_sub_type');
    }
}
