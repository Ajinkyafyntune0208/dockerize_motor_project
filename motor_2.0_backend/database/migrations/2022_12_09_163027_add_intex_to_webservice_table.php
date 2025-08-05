<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIntexToWebserviceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('webservice_request_response_data', function (Blueprint $table) {
            $table->longText('message')->change();
            $table->index(['status', "created_at", "method_name"], 'webSer_req_res_index');
        });
        Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
            $table->longText('message')->change();
            $table->index(['status', "created_at", "method_name"], 'quote_webSer_req_res_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}
