<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHdfcErgoModelMasterTable extends Migration
{
    public function up()
    {
        Schema::create('hdfc_ergo_model_master', function (Blueprint $table) {

		$table->integer('manufacturer_code')->nullable();
		$table->integer('version_id', true);
		$table->integer('vehicle_class_code')->nullable();
		$table->integer('vehicle_subclass_code')->nullable();
		$table->string('manufacturer',100)->nullable();
		$table->integer('vehicle_model_code')->nullable();
		$table->string('vehicle_model',100)->nullable();
		$table->integer('number_of_wheels')->nullable();
		$table->integer('cubic_capacity')->nullable();
		$table->integer('gross_vehicle_weight')->nullable();
		$table->integer('seating_capacity')->nullable();
		$table->integer('carrying_capacity')->nullable();
		$table->integer('tab_row_index')->nullable();
		$table->string('txt_fuel',100)->nullable();
		$table->string('txt_segment_type',100)->nullable();
		$table->string('txt_variant',100)->nullable();
		$table->enum('is_active',['1','0'])->nullable();

        });
    }

    public function down()
    {
        Schema::dropIfExists('hdfc_ergo_model_master');
    }
}