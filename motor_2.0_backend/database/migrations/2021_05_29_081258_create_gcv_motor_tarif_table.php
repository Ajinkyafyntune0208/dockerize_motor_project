<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGcvMotorTarifTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gcv_motor_tarif', function (Blueprint $table) {
            $table->integer('tattif_id', true);
            $table->integer('vehicle_use_id')->default(0);
            $table->integer('zone_id')->nullable();
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->integer('min_weight')->nullable();
            $table->integer('max_weight')->nullable();
            $table->double('rate')->nullable();
            $table->integer('exceeding_interval_unit')->nullable();
            $table->integer('exceeding_interval_value')->nullable();
            $table->dateTime('effective_todate')->nullable();
            $table->dateTime('effective_fromdate')->nullable();
            $table->double('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
            $table->string('status')->nullable()->default('Active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gcv_motor_tarif');
    }
}
