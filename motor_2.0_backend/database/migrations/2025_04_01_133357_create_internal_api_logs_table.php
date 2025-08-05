<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalApiLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('internal_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_id', 150)->nullable()->index();
            $table->string('endpoint', 150)->nullable()->index();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('internal_api_logs');
    }
}
