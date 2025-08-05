<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MigrationDataReports extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('migration_data_reports')) 
        {
            Schema::create('migration_data_reports', function (Blueprint $table) {
                $table->id();
                $table->string('vehicle_reg_no')->nullable();
                $table->string('policy_no')->nullable();
                $table->integer('user_product_journey_id')->nullable();
                $table->string('source')->nullable();
                $table->string('comments')->nullable();
                $table->string('policy_status')->nullable();
                $table->string('status')->nullable();
                $table->date('transaction_date')->nullable();
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
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
