<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebserviceRequestResponseDataOptionListTable extends Migration
{
    public function up()
    {
        Schema::create('webservice_request_response_data_option_list', function (Blueprint $table) {
            $table->unsignedBigInteger('list_id', true);
            $table->string('company')->nullable();
            $table->string('section')->nullable();
            $table->string('method_name')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('webservice_request_response_data_option_list');
    }
}
