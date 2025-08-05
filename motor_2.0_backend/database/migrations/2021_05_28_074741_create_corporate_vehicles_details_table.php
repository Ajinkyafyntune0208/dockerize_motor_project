<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorporateVehiclesDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corporate_vehicles_details', function (Blueprint $table) {
            $table->bigInteger('corporate_vihicle_detail_id', true);
            $table->integer('corp_id');
            $table->string('vehicle_make', 50);
            $table->string('vehicle_model', 50);
            $table->string('vehicle_version', 50);
            $table->integer('ft_version_id')->default(0);
            $table->string('registering_on_name');
            $table->integer('registration_month')->default(0);
            $table->integer('registration_year')->default(0);
            $table->integer('manufacture_year')->default(0);
            $table->integer('manufacture_month')->default(0);
            $table->integer('exshowroom_prize')->default(0);
            $table->integer('rto_code_id')->default(0);
            $table->string('status')->default('0');
            $table->string('claim_in_expiring_policy');
            $table->integer('ncb_percentage_in_expiring_policy')->default(0);
            $table->dateTime('expiry_date');
            $table->string('engine_number', 50)->default('');
            $table->string('chassis_number', 50)->default('');
            $table->string('registration_number', 50)->default('');
            $table->integer('created_by');
            $table->dateTime('created_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corporate_vehicles_details');
    }
}
