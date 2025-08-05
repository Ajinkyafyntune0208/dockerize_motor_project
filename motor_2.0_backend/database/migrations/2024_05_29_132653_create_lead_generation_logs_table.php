<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadGenerationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('lead_generation_logs')) {
            Schema::create('lead_generation_logs', function (Blueprint $table) {
                $table->id();
                $table->integer('enquiry_id');
                $table->longText('request');
                $table->longText('response');
                $table->timestamps();
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
        Schema::dropIfExists('lead_generation_logs');
    }
}
