<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDatapushReqResTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('datapush_req_res', function (Blueprint $table) {
            $table->id();
            $table->integer('enquiry_id');
            $table->string('url')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('dataenc')->nullable();
            $table->json('datadenc')->nullable();
            $table->enum('status', ['SUCCESS', 'FAILED'])->nullable();
            $table->integer('status_code')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
            $table->index("created_at");
            $table->index("enquiry_id");
            $table->index("status");
            $table->index("status_code");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('datapush_req_res');
    }
}
