<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToProposalVehicleValidationLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal_vehicle_validation_logs', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $index_list = $sm->listTableIndexes('proposal_vehicle_validation_logs');

            // check if the indexing is already done or not
            if (!in_array('proposal_vehicle_validation_logs_vehicle_reg_no', array_keys($index_list))) {
                $table->index('vehicle_reg_no', 'proposal_vehicle_validation_logs_vehicle_reg_no');
            }

            if (!in_array('proposal_vehicle_validation_logs_service_type', array_keys($index_list))) {
                $table->index('service_type', 'proposal_vehicle_validation_logs_service_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('proposal_vehicle_validation_logs', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $index_list = $sm->listTableIndexes('proposal_vehicle_validation_logs');

            // check if the indexing is already done or not
            if (in_array('proposal_vehicle_validation_logs_vehicle_reg_no', array_keys($index_list))) {
                $table->dropIndex('vehicle_reg_no', 'proposal_vehicle_validation_logs_vehicle_reg_no');
            }

            if (in_array('proposal_vehicle_validation_logs_service_type', array_keys($index_list))) {
                $table->dropIndex('service_type', 'proposal_vehicle_validation_logs_service_type');
            }
        });
    }
}
