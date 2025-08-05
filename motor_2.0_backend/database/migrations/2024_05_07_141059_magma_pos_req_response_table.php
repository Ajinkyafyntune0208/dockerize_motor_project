<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MagmaPosReqResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('magma_pos_req_response', function (Blueprint $table) {

            $table->id('id');
            $table->integer('agent_id');
            $table->string('company')->nullable();
            $table->string('section', 100)->nullable();
            $table->string('method_name', 255)->nullable();
            $table->string('product')->nullable();
            $table->string('method')->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->longText('endpoint_url')->nullable();
            $table->string('headers')->nullable();
            $table->string('message')->nullable();
            $table->string('status')->nullable();
            $table->string('response_time')->nullable();
            $table->datetime('created_at')->nullable();
            $table->datetime('start_time')->nullable();
		    $table->datetime('end_time')->nullable();
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('magma_pos_req_response');
    }
}
