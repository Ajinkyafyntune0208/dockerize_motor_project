<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCkycLogsRequestResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ckyc_logs_request_responses', function (Blueprint $table) {
            if ( ! Schema::hasTable('ckyc_logs_request_responses')) {
                $table->id();
                $table->string('enquiry_id')->nullable();
                $table->string('company_alias')->nullable();
                $table->string('mode')->nullable();
                $table->json('request')->nullable();
                $table->json('response')->nullable();
                $table->json('headers')->nullable();
                $table->string('endpoint_url')->nullable();
                $table->string('status')->nullable();
                $table->string('failure_message')->nullable();
                $table->string('ip_address')->nullable();
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->string('response_time')->nullable();
                $table->timestamps();
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
        if (Schema::hasTable('ckyc_logs_request_responses')) {
            Schema::dropIfExists('ckyc_logs_request_responses');
        }
    }
}
