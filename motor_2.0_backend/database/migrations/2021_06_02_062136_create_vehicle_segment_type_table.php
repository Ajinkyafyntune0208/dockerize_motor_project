<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleSegmentTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_segment_type', function (Blueprint $table) {
            $table->integer('segment_type_id', true);
            $table->string('segment_type', 50)->default('');
            $table->boolean('is_active')->default(0);
            $table->dateTime('createdon')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_segment_type');
    }
}
