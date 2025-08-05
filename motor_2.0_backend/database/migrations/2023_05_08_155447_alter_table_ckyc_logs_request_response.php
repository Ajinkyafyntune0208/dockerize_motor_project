<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableCkycLogsRequestResponse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::update(DB::RAW("UPDATE ckyc_logs_request_responses r SET r.enquiry_id = (RIGHT(r.enquiry_id, 8) * 1) WHERE r.enquiry_id IS NOT NULL"));

        DB::update(DB::RAW("UPDATE ckyc_logs_request_responses r SET r.`status` = 'Failed' WHERE r.`status` = 'Failure'"));

        Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
            $table->bigInteger('enquiry_id')->nullable()->default(NULL)->change();
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
