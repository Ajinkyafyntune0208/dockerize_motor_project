<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChollaMandalamCvRtoMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cholla_mandalam_cv_rto_master')) {
            Schema::create('cholla_mandalam_cv_rto_master', function (Blueprint $table) {
                $table->id('id');
                $table->integer('num_state_code')->nullable();
                $table->integer('num_city_district_code')->nullable();
                $table->string('txt_rto_location_code', 10)->nullable();
                $table->string('txt_rto_location_desc', 255)->nullable();
                $table->integer('num_vehicle_class_code')->nullable();
                $table->string('txt_registration_zone', 10)->nullable();
                $table->string('num_vehicle_subclass_code', 255)->nullable();
                $table->string('active_flag', 10)->nullable();
                $table->string('txt_registration_state_code', 10)->nullable();
                $table->integer('num_registration_rto_code')->nullable();
                $table->integer('alternate_rto')->nullable();
                $table->string('district_name', 255)->nullable();
                $table->string('rto', 10)->nullable();
                $table->string('rto_location_for_schedule_printing', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cholla_mandalam_cv_rto_master');
    }
}