<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexingOnPolicySmsOtpsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_sms_otps', function (Blueprint $table) {
            $table->bigInteger('enquiryId')->nullable()->change();
            $table->integer('otp')->nullable()->change();
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('policy_sms_otps');
            $indexes = collect($indexes);
            $index_list = [];
            if ($indexes->isNotEmpty()) {
                $indexes->each(function ($items) use (&$index_list) {
                    $indexName = $items->getName();
                    $index_list[$indexName] = $indexName;
                });
            }
            // check if the indexing is already done or not
            if (!in_array('policy_sms_otps_enquiryId', $index_list)) {
                $table->index('enquiryId', 'policy_sms_otps_enquiryId');
            }
            if (!in_array('policy_sms_otps_is_expired', $index_list)) {
                $table->index('is_expired', 'policy_sms_otps_is_expired');
            }
            if (!in_array('policy_sms_otps_otp', $index_list)) {
                $table->index('otp', 'policy_sms_otps_otp');
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
        Schema::table('policy_sms_otps', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('policy_sms_otps');
            $indexes = collect($indexes);
            $index_list = [];
            if ($indexes->isNotEmpty()) {
                $indexes->each(function ($items) use (&$index_list) {
                    $indexName = $items->getName();
                    $index_list[$indexName] = $indexName;
                });
            }
            // check if the indexing is already done or not
            if (in_array('policy_sms_otps_enquiryId', $index_list)) {
                $table->dropIndex('policy_sms_otps_enquiryId');
            }
            if (in_array('policy_sms_otps_is_expired', $index_list)) {
                $table->dropIndex('policy_sms_otps_is_expired');
            }
            if (in_array('policy_sms_otps_otp', $index_list)) {
                $table->dropIndex('policy_sms_otps_otp');
            }
        });
    }
}
