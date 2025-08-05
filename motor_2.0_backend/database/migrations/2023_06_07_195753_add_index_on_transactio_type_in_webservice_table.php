<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexOnTransactioTypeInWebserviceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('webservice_request_response_data', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $index_list = $sm->listTableIndexes('webservice_request_response_data');

            // check if the indexing is already done or not
            if (!in_array('webservice_request_response_data_transaction_type', array_keys($index_list))) {
                $table->index('transaction_type', 'webservice_request_response_data_transaction_type');
            }
        });

        Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $index_list = $sm->listTableIndexes('quote_webservice_request_response_data');

            // check if the indexing is already done or not
            if (!in_array('quote_webservice_request_response_data_transaction_type', array_keys($index_list))) {
                $table->index('transaction_type', 'quote_webservice_request_response_data_transaction_type');
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
        Schema::table('webservice_request_response_data', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $index_list = $sm->listTableIndexes('webservice_request_response_data');

            // check if the indexing is already done or not
            if (in_array('webservice_request_response_data_transaction_type', array_keys($index_list))) {
                $table->dropIndex('webservice_request_response_data_transaction_type');
            }
        });

        Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $index_list = $sm->listTableIndexes('quote_webservice_request_response_data');

            // check if the indexing is already done or not
            if (in_array('quote_webservice_request_response_data_transaction_type', array_keys($index_list))) {
                $table->dropIndex('quote_webservice_request_response_data_transaction_type');
            }
        });
    }
}
