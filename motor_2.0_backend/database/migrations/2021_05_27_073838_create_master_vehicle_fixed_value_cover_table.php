<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterVehicleFixedValueCoverTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_vehicle_fixed_value_cover', function (Blueprint $table) {
            $table->integer('cover_id', true);
            $table->string('cover_name', 50)->default('');
            $table->string('cover_code', 50)->default('');
            $table->boolean('isactive')->default(0);
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
        Schema::dropIfExists('master_vehicle_fixed_value_cover');
    }
}
