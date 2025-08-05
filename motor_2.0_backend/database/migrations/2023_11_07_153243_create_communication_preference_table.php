<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunicationPreferenceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('communication_preference', function (Blueprint $table) {

            $table->id();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->enum('on_call',['Y','N']);
            $table->enum('on_sms',['Y','N']);
            $table->enum('on_email',['Y','N']);
            $table->enum('on_whatsapp',['Y','N']);
            $table->timestamps();

            $table->index('mobile');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('communication_preference');
    }
}
