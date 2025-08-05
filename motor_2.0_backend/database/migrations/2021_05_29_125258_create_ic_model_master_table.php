<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcModelMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_model_master', function (Blueprint $table) {
            $table->integer('ic_model_master_id', true);
            $table->integer('company_id');
            $table->string('manufacture_name', 50);
            $table->string('model_name', 50);
            $table->string('version_name', 50);
            $table->string('veh_type_name', 50);
            $table->integer('veh_type_id_fk');
            $table->string('showroom_price_min', 50)->nullable();
            $table->string('showroom_price_max', 50)->nullable();
            $table->string('wheels', 50)->nullable();
            $table->text('operated_by')->nullable();
            $table->string('cubic_capacity', 50)->nullable();
            $table->string('seating_capacity', 50)->nullable();
            $table->string('carrying_capacity', 50)->nullable();
            $table->string('model_id', 50);
            $table->string('manufacture_id', 50);
            $table->integer('version_id');
            $table->integer('ft_manufacture_id');
            $table->integer('ft_model_id');
            $table->integer('ft_version_id')->nullable();
            $table->string('status')->default('Active');
            $table->integer('created_by');
            $table->dateTime('created_date');
            $table->integer('updated_by');
            $table->dateTime('updated_date');
            $table->integer('deleted_by');
            $table->dateTime('deleted_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ic_model_master');
    }
}
