<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableProposalVehicleValidationLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_vehicle_validation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_reg_no')->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->string('endpoint_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal_vehicle_validation_logs');
    }
}
