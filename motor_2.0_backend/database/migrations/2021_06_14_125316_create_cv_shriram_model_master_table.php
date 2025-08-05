<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvShriramModelMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_shriram_model_master', function (Blueprint $table) {
            $table->string('veh_code')->nullable();
            $table->string('veh_model')->nullable();
            $table->string('model_desc')->nullable();
            $table->string('veh_body')->nullable();
            $table->string('body_desc')->nullable();
            $table->string('veh_cc')->nullable();
            $table->string('veh_gvw')->nullable();
            $table->string('veh_fuel')->nullable();
            $table->string('fuel')->nullable();
            $table->string('veh_make')->nullable();
            $table->string('manf')->nullable();
            $table->string('veh_seat_cap')->nullable();
            $table->string('veh_no_driver')->nullable();
            $table->string('veh_flex_03')->nullable();
            $table->string('vap_prod_code')->nullable();
            $table->string('veh_cr_dt')->nullable();
            $table->string('veh_upd_dt')->nullable();
            $table->string('stfc_all')->nullable();
            $table->string('mis_d_category')->nullable();
            $table->string('veh_category')->nullable();
            $table->string('veh_remarks')->nullable();
            $table->string('veh_ob_year')->nullable();
            $table->string('veh_ob_type')->nullable();
            $table->string('veh_min_si')->nullable();
            $table->string('veh_flex_09')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cv_shriram_model_master');
    }
}
