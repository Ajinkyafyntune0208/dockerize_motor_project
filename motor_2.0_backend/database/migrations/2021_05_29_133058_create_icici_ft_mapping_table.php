<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciFtMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_ft_mapping', function (Blueprint $table) {
            $table->integer('icici_ft_mapping_id', true);
            $table->string('manf_name', 100)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('version_name', 100)->nullable();
            $table->double('vehicle_model_code')->nullable();
            $table->string('cc', 50)->nullable();
            $table->string('fuel', 50)->nullable();
            $table->string('carrying', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_ft_mapping');
    }
}
