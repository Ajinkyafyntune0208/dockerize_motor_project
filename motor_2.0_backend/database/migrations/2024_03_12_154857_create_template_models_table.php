<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTemplateModelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('template_models', function (Blueprint $table) {
            $table->id('template_id');
            $table->string('title')->nullable();
            $table->string('alias');
            $table->enum('communication_type', ['email', 'sms', 'whatsapp']);
            $table->longText('content');
            $table->enum('status', ['Y', 'N'])->default('Y');
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
        Schema::dropIfExists('template_models');
    }
}
