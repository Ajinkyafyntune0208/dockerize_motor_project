<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVahanExportLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vahan_export_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('uid');
            $table->index('uid');
            $table->string('source');
            $table->text('request');
            $table->string('file');
            $table->dateTime('file_expiry');
            $table->integer('file_deleted');
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
        Schema::dropIfExists('vahan_export_logs');
    }
}
