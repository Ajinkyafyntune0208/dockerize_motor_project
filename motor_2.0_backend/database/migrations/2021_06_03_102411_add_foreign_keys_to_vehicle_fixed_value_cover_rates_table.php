<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToVehicleFixedValueCoverRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_fixed_value_cover_rates', function (Blueprint $table) {
            $table->foreign('cover_id', 'FK_vehicle_fixed_value_cover')->references('cover_id')->on('master_vehicle_fixed_value_cover')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vehicle_fixed_value_cover_rates', function (Blueprint $table) {
            $table->dropForeign('FK_vehicle_fixed_value_cover');
        });
    }
}
