<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusMessageResponsibleToQuoteWebserviceRequestResponseData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('quote_webservice_request_response_data','status')) {
            Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
                $table->string('status')->nullable();
            });
        }

        if (!Schema::hasColumn('quote_webservice_request_response_data', 'message')) {
            Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
                $table->string('message')->nullable();
            });
        }

        if (!Schema::hasColumn('quote_webservice_request_response_data', 'responsible')) {
            Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
                $table->string('responsible')->nullable();
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
        Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
            $table->dropColumn(['status', 'message', 'responsible']);
        });
    }
}
