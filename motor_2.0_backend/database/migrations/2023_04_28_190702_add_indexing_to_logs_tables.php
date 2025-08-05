<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexingToLogsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('ckyc_logs_request_responses')) {
            Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $index_list = $sm->listTableIndexes('ckyc_logs_request_responses');

                // $table->unsignedBigInteger('enquiry_id')->change();

                // check if the indexing is already done or not
                if (!in_array('ckyc_logs_request_responses_enquiry_id', array_keys($index_list))) {
                    $table->index('enquiry_id', 'ckyc_logs_request_responses_enquiry_id');
                }

                if (!in_array('ckyc_logs_request_responses_company_alias', array_keys($index_list))) {
                    $table->index('company_alias', 'ckyc_logs_request_responses_company_alias');
                }

                if (!in_array('ckyc_logs_request_responses_status', array_keys($index_list))) {
                    $table->index('status', 'ckyc_logs_request_responses_status');
                }

                if (!in_array('ckyc_logs_request_responses_created_at', array_keys($index_list))) {
                    $table->index('created_at', 'ckyc_logs_request_responses_created_at');
                }

                // DB::update(DB::RAW("UPDATE ckyc_logs_request_responses r SET r.enquiry_id = (SUBSTRING(r.enquiry_id, 9) * 1) WHERE r.enquiry_id IS NOT NULL"));

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
        if (Schema::hasTable('ckyc_logs_request_responses')) {
            Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $index_list = $sm->listTableIndexes('ckyc_logs_request_responses');

                // check if the indexing is already done or not
                if (in_array('ckyc_logs_request_responses_enquiry_id', array_keys($index_list))) {
                    $table->dropIndex('ckyc_logs_request_responses_enquiry_id');
                }

                if (in_array('ckyc_logs_request_responses_company_alias', array_keys($index_list))) {
                    $table->dropIndex('ckyc_logs_request_responses_company_alias');
                }

                if (in_array('ckyc_logs_request_responses_status', array_keys($index_list))) {
                    $table->dropIndex('ckyc_logs_request_responses_status');
                }

                if (in_array('ckyc_logs_request_responses_created_at', array_keys($index_list))) {
                    $table->dropIndex('ckyc_logs_request_responses_created_at');
                }
            });
        }
    }
}
