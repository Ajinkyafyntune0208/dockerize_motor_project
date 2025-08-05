<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorServerErrorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('motor_server_errors')) {
            Schema::create('motor_server_errors', function (Blueprint $table) {
                $table->id();
                $table->longText('url')->nullable();
                $table->longText('request')->nullable();
                $table->longText('error')->nullable();
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
        Schema::dropIfExists('motor_server_errors');
    }
}
