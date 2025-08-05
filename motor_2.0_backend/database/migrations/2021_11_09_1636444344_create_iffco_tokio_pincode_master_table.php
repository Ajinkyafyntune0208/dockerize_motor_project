<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIffcoTokioPincodeMasterTable extends Migration
{
    public function up()
    {
        Schema::create('iffco_tokio_pincode_master', function (Blueprint $table) {

		$table->integer('pincode')->nullable();
		$table->string('state')->nullable();
		$table->string('statecode')->nullable();
		$table->string('district')->nullable();
		$table->string('city')->nullable();
		$table->string('country')->nullable();

        });
    }

    public function down()
    {
        Schema::dropIfExists('iffco_tokio_pincode_master');
    }
}