<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusMessageResponsibleToWebserviceRequestResponseData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('webservice_request_response_data','status')) {
            Schema::table('webservice_request_response_data', function (Blueprint $table) {
                $table->string('status')->nullable();
            });
        }

        if (!Schema::hasColumn('webservice_request_response_data', 'message')) {
            Schema::table('webservice_request_response_data', function (Blueprint $table) {
                $table->string('message')->nullable();
            });
        }

        if (!Schema::hasColumn('webservice_request_response_data', 'responsible')) {
            Schema::table('webservice_request_response_data', function (Blueprint $table) {
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
        Schema::table('webservice_request_response_data', function (Blueprint $table) {
            $table->dropColumn(['status', 'message', 'responsible']);
        });
    }
}
