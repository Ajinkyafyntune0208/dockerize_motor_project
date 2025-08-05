<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeMessageColumnType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasColumn('quote_webservice_request_response_data', 'message')){
            Schema::table('quote_webservice_request_response_data', function (Blueprint $table) {
                $table->longText('message')->change();
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
            
        });
    }
}
