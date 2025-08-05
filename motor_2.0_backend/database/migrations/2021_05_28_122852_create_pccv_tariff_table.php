<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePccvTariffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pccv_tariff', function (Blueprint $table) {
            $table->integer('tattif_id', true);
            $table->integer('vehicle_use_id')->default(0);
            $table->integer('zone_id')->nullable();
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->integer('min_cc')->nullable();
            $table->integer('max_cc')->nullable();
            $table->double('rate')->nullable();
            $table->integer('min_passenger')->nullable();
            $table->integer('max_passenger')->nullable();
            $table->integer('additional_value')->default(0);
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
        Schema::dropIfExists('pccv_tariff');
    }
}
