<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunicationConfigurationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('communication_configuration', function (Blueprint $table) {
            $table->id();
            $table->string('page_name');
            $table->string('slug');
            $table->boolean('email_is_enable')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('sms_is_enable')->default(true);
            $table->boolean('sms')->default(false);
            $table->boolean('whatsapp_api_is_enable')->default(true);
            $table->boolean('whatsapp_api')->default(false);
            $table->boolean('whatsapp_redirection_is_enable')->default(true);
            $table->boolean('whatsapp_redirection')->default(false);
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
        Schema::dropIfExists('communication_configuration');
    }
}