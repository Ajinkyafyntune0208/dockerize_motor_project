<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebserviceRequestResponseDataTable extends Migration
{
    public function up()
    {
        Schema::create('webservice_request_response_data', function (Blueprint $table) {

		$table->id('id');
		$table->unsignedInteger('enquiry_id')->nullable();
		$table->string('company')->nullable();
		$table->string('section', 100)->nullable();
		$table->string('method_name', 255)->nullable();
		$table->string('product')->nullable();
		$table->string('method')->nullable();
		$table->longText('request')->nullable();
		$table->longText('response')->nullable();
		$table->longText('endpoint_url')->nullable();
		$table->string('ip_address')->nullable();
		$table->datetime('start_time')->nullable();
		$table->datetime('end_time')->nullable();
		$table->string('response_time')->nullable();
		$table->datetime('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('webservice_request_response_data');
    }
}
