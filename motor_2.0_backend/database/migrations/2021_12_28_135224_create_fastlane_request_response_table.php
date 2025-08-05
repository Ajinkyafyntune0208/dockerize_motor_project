<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFastlaneRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fastlane_request_response', function (Blueprint $table) {
            $table->id();
            $table->integer('enquiry_id');
            $table->text('transaction_type');
            $table->text('request');
            $table->text('response');
            $table->text('endpoint_url');
            $table->text('ip_address');
            $table->time('response_time');
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fastlane_request_response');
    }
}
