<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosConfigLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_config_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->nullable();
            $table->string('operation_type', 20)->nullable();
            $table->string('url', 150)->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->bigInteger('user_id')->nullable();
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
        Schema::dropIfExists('pos_config_logs');
    }
}
