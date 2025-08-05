<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexQuoteWebserviceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('quote_webservice_request_response_data')) {
            Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
                $table->index(['enquiry_id', 'company']);
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
        if (Schema::hasTable('quote_webservice_request_response_data')) {
            Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
                $table->dropIndex(['enquiry_id', 'company']);
            });
        }
    }
}
