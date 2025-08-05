<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexInVahanServiceLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('vahan_service_logs')) {
            Schema::table('vahan_service_logs', function (Blueprint $table) {
                $table->index('enquiry_id');
                $table->index('stage');
                $table->index('vehicle_reg_no');
                $table->index('created_at');
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
        if (Schema::hasTable('vahan_service_logs')) {
            Schema::table('vahan_service_logs', function (Blueprint $table) {
                $table->removeIndex('enquiry_id');
                $table->removeIndex('stage');
                $table->removeIndex('vehicle_reg_no');
                $table->removeIndex('created_at');
            });
        }
    }
}
