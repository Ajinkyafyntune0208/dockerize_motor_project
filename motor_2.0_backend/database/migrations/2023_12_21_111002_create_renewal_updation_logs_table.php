<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRenewalUpdationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renewal_updation_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->bigInteger('renewal_data_migration_status_id')->nullable();
            $table->string('previous_policy_number')->nullable();
            $table->string('vehicle_registration_number')->nullable();
            $table->text('old_data')->nullable();
            $table->text('new_data')->nullable();
            $table->string('type')->nullable();
            $table->index('user_product_journey_id');
            $table->index('previous_policy_number');
            $table->index('type');
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
        Schema::dropIfExists('renewal_updation_logs');
    }
}
