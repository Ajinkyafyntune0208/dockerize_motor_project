<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVahanServicePriorityListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('vahan_service_priority_list')) {
            Schema::dropIfExists('vahan_service_priority_list');
        }
        Schema::create('vahan_service_priority_list', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_type');
            $table->string('journey_type');
            $table->integer('vahan_service_id');
            $table->integer('priority_no');
            $table->string('integration_process');
            $table->text('B2B_employee_decision');
            $table->text('B2B_pos_decision');
            $table->text('B2B_partner_decision');
            $table->text('B2C_decision');
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
        Schema::dropIfExists('vahan_service_priority_list');
    }
}
