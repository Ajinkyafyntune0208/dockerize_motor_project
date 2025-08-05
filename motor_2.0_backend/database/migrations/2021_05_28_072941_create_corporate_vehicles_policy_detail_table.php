<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorporateVehiclesPolicyDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corporate_vehicles_policy_detail', function (Blueprint $table) {
            $table->bigInteger('corporate_policy_detail_id', true);
            $table->bigInteger('corporate_vehicle_detail_id')->nullable();
            $table->bigInteger('proposal_id')->nullable();
            $table->string('policy_number', 50)->nullable();
            $table->string('vehicle_idv', 50)->nullable();
            $table->string('registration_number', 50)->nullable();
            $table->string('claim_in_expiring_policy');
            $table->integer('ncb_percentage_in_expiring_policy')->default(0);
            $table->dateTime('expiry_date');
            $table->dateTime('start_date')->nullable();
            $table->string('status')->default('1');
            $table->string('last_newal_us');
            $table->bigInteger('master_policy_id')->nullable();
            $table->integer('createdby')->default(0);
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
        Schema::dropIfExists('corporate_vehicles_policy_detail');
    }
}
