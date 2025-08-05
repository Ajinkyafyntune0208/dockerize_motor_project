<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePdfRequestResponse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pdf_request_response', function (Blueprint $table) {
            $table->bigInteger('enquiry_id');
            $table->string('type')->nullable();
            $table->longText('payload')->nullable();
            $table->string('response')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pdf_request_response');
    }
}