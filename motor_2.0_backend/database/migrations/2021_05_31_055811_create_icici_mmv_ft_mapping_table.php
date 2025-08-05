<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciMmvFtMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_mmv_ft_mapping', function (Blueprint $table) {
            $table->double('version_id')->nullable();
            $table->string('manf_name', 100)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('version_name', 100)->nullable();
            $table->double('vehicle_model_code')->nullable();
            $table->double('cubic_capacity')->nullable();
            $table->string('fuel_type', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_mmv_ft_mapping');
    }
}
