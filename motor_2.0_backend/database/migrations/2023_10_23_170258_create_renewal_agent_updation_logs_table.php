<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRenewalAgentUpdationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renewal_agent_updation_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->bigInteger('renewal_data_migration_status_id')->nullable();
            $table->string('previous_policy_number')->nullable();
            $table->string('vehicle_registration_number')->nullable();
            $table->text('agent_old_data')->nullable();
            $table->text('agent_new_data')->nullable();
            $table->index('user_product_journey_id');
            $table->index('previous_policy_number');
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
        Schema::dropIfExists('renewal_agent_updation_logs');
    }
}
