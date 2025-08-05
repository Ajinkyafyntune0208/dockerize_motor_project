<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterProductTable extends Migration
{
    public function up()
    {
        Schema::create('master_product', function (Blueprint $table) {

		$table->id('product_id');
		$table->string('product_name')->nullable();
		$table->unsignedBigInteger('master_policy_id')->nullable();
		$table->string('product_identifier')->nullable();
		$table->string('ic_id')->nullable();
		$table->enum('status',['Active','Inactive'])->default('Active');
        $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('master_product');
    }
}