<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMailLogsTable extends Migration
{
    public function up()
    {
        Schema::create('mail_logs', function (Blueprint $table) {
		$table->id('id');
		$table->string('email_id')->nullable();
		$table->string('mobile_no')->nullable();
		$table->string('first_name')->nullable();
		$table->string('last_name')->nullable();
		$table->string('subject')->nullable();
		$table->text('mail_body')->nullable();
		$table->integer('enquiryId')->nullable();
		$table->enum('status',['Y','N'])->nullable()->default('N');
		$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mail_logs');
    }
}