<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnJsonDataTableHyundaiMigrationDataReports extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('hyundai_migration_data_reports')) {
            Schema::table('hyundai_migration_data_reports', function (Blueprint $table) {
                $table->longText('json_data')->nullable();
                $table->index('user_product_journey_id');
                $table->index('vehicle_reg_no');
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
        //
    }
}
