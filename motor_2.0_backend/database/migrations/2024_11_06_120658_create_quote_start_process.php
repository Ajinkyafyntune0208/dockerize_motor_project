<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuoteStartProcess extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('quote_start_process')) 
        {
            Schema::create('quote_start_process', function (Blueprint $table) 
            {
                $table->id();
                $table->integer('enquiry_id')->index();
                $table->smallInteger('section')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response')->nullable();
                $table->smallInteger('status')->default(1);
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
        Schema::dropIfExists('quote_start_process');
    }
}
