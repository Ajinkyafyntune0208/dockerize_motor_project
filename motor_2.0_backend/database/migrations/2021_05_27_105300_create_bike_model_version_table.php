<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBikeModelVersionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bike_model_version', function (Blueprint $table) {
            $table->increments('version_id');
            $table->unsignedInteger('model_id')->index('FK_MI_ModelVersion_MI_Model');
            $table->string('version_name', 200)->default('');
            $table->decimal('cubic_capacity', 5, 0)->comment('CC');
            $table->string('fuel_type', 50);
            $table->string('seating_capacity', 50);
            $table->boolean('is_discontinued')->default(0);
            $table->string('mmv_bajaj_allianz', 20)->nullable();
            $table->string('mmv_aditya_birla', 20)->nullable();
            $table->string('mmv_future_generali', 20)->nullable();
            $table->string('mmv_liberty_videocon', 20)->nullable();
            $table->string('mmv_tata_aig', 20)->nullable();
            $table->string('mmv_shriram', 20)->nullable();
            $table->string('mmv_bharti_axa', 20)->nullable();
            $table->string('mmv_reliance', 20)->nullable();
            $table->string('mmv_hdfc_ergo', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bike_model_version');
    }
}
