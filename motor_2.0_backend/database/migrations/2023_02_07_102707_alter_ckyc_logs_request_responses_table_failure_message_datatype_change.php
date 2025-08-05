<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterCkycLogsRequestResponsesTableFailureMessageDatatypeChange extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
            $table->string('enquiry_id', 30)->nullable()->change();
            $table->string('company_alias', 40)->nullable()->change();
            $table->string('mode', 30)->nullable()->change();
            $table->string('status', 15)->nullable()->change();
            $table->text('failure_message')->nullable()->change();
            $table->string('ip_address', 20)->nullable()->change();
            $table->string('response_time', 10)->nullable()->change();
        });
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
