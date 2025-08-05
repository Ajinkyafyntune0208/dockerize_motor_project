<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleFixedValueCoverRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_fixed_value_cover_rates', function (Blueprint $table) {
            $table->integer('rate_id', true);
            $table->integer('cover_id')->index('FK_vehicle_fixed_value_cover');
            $table->double('cover_rate')->default(0);
            $table->string('rate_type', 50)->default('0');
            $table->integer('base_si')->default(0);
            $table->integer('max_value')->default(0);
            $table->integer('product_sub_type_id')->default(0);
            $table->integer('company_id')->default(0);
            $table->boolean('isactive')->default(0);
            $table->dateTime('createdon')->useCurrent();
			
			
			// $table->foreign('cover_id', 'FK_vehicle_fixed_value_cover')->references('cover_id')->on('master_vehicle_fixed_value_cover')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_fixed_value_cover_rates');
    }
}
