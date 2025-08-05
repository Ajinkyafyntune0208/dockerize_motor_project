<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLsqServiceLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('lsq_service_logs'))
        {
            Schema::create('lsq_service_logs', function (Blueprint $table) {
                $table->id();
                $table->string('enquiry_id')->nullable();
                $table->string('transaction_type')->nullable();
                $table->string('method')->nullable();
                $table->string('method_name')->nullable();
                $table->longText('request')->nullable();
                $table->longText('response')->nullable();
                $table->text('endpoint_url')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('response_time')->nullable();
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->text('headers')->nullable();
                $table->timestamps();
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
        Schema::dropIfExists('lsq_service_logs');
    }
}
