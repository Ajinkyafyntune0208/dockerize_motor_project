<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('history', function (Blueprint $table) {
            $table->integer('history_id', true);
            $table->string('module_name', 50);
            $table->string('previous_data', 8000)->nullable();
            $table->string('updated_data', 8000)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->string('user_id', 100)->nullable();
            $table->integer('role_id')->nullable();
            $table->string('ip_address', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('history');
    }
}
