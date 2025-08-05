<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorManufacturerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor_manufacturer', function (Blueprint $table) {
            $table->integer('manf_id', true);
            $table->string('manf_name', 200);
            $table->string('is_discontinued')->default('Y');
            $table->integer('product_sub_type_id')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('modified_at')->nullable();
            $table->softDeletes();
            $table->string('status')->default('Active');
            $table->string('img', 500)->nullable();
            $table->integer('priority');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_manufacturer');
    }
}
