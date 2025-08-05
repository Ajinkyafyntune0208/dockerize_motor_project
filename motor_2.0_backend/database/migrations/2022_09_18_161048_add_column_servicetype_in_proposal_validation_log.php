<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnServicetypeInProposalValidationLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('proposal_vehicle_validation_logs', 'service_type')) {
            Schema::table('proposal_vehicle_validation_logs', function (Blueprint $table) {
                $table->string('service_type', 50)->nullable()->after('vehicle_reg_no');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('proposal_vehicle_validation_logs', 'service_type')) {
            Schema::table('proposal_vehicle_validation_logs', function (Blueprint $table) {
                $table->dropColumn('service_type');
            });

        }
    }
}
