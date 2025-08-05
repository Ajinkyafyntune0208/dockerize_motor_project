<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnChecksumPolicyIdTableQuoteWebserviceRequestResponseData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('quote_webservice_request_response_data', 'policy_id'))
        {
            Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
                $table->smallInteger('policy_id')->nullable();
                $table->longText('checksum')->nullable();
                $table->fullText('checksum');
            });
        }
        // if (!Schema::hasColumn('webservice_request_response_data', 'policy_id'))
        // {
        //     Schema::table('webservice_request_response_data', function (Blueprint $table) {
        //         $table->smallInteger('policy_id')->nullable();
        //         $table->longText('checksum')->nullable();
        //         $table->fullText('checksum');
        //     });
        // }
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
