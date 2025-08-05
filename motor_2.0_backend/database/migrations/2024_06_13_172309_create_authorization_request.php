<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthorizationRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('authorization_request')) 
        {
            Schema::create('authorization_request', function (Blueprint $table) {
                $table->id('authorization_request_id');
                $table->string('reference_model')->nullable();
                $table->string('reference_table')->nullable();
                $table->string('reference_update_column')->nullable();
                $table->string('reference_update_value')->nullable();
                $table->string('old_value')->nullable();
                $table->string('new_value')->nullable();
                $table->string('requested_by')->nullable();
                $table->timestamp('requested_date')->nullable();
                $table->longText('request_comment')->nullable();
                $table->string('reference_search_key')->nullable();
                $table->string('reference_search_value')->nullable();
                $table->string('approved_by')->nullable();
                $table->timestamp('approved_date')->nullable();
                $table->string('approved_status')->default('N');
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
        Schema::dropIfExists('authorization_request');
    }
}
