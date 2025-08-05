<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciRtoMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_rto_master', function (Blueprint $table) {
            $table->integer('icici_rto_master_id', true);
            $table->string('rto_code', 10)->nullable();
            $table->string('rto_location_code', 150)->nullable();
            $table->string('state_code', 150)->nullable();
            $table->string('state_name', 150)->nullable();
            $table->string('city_code', 100)->nullable();
            $table->string('city_name', 200)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('private_car_class_code_45', 50)->nullable();
            $table->string('passanger_carrying_class_code_41', 50)->nullable();
            $table->integer('product_sub_type_id')->nullable();
            $table->string('status')->default('Active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_rto_master');
    }
}
