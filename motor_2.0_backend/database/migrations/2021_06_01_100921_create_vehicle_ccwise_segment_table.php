<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleCcwiseSegmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_ccwise_segment', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('vehicle_ccwise_segment', 50)->nullable();
            $table->integer('min_cc')->nullable();
            $table->integer('max_cc')->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->dateTime('createdon')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_ccwise_segment');
    }
}
