<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInspectionStatusLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasTable('inspection_status_logs')) {
            Schema::create('inspection_status_logs', function (Blueprint $table) {
                $table->id();
                $table->string('ic_name')->nullable();
                $table->string('segment')->nullable();
                $table->string('breakin_id')->nullable();
                $table->json('request')->nullable();
                $table->json('response')->nullable();
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
        Schema::dropIfExists('inspection_status_logs');
    }
}