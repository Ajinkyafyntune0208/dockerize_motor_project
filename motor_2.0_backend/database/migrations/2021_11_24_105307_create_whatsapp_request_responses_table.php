<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappRequestResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whatsapp_request_responses', function (Blueprint $table) {
            $table->id();
            $table->string('ip')->nullable();
            $table->string('enquiry_id')->nullable();
            $table->string('request_id')->nullable();
            $table->string('mobile_no')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->json('additional_data')->nullable();
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
        Schema::dropIfExists('whatsapp_request_responses');
    }
}
