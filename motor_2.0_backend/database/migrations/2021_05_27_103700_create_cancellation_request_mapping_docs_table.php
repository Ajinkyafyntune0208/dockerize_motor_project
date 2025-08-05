<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCancellationRequestMappingDocsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cancellation_request_mapping_docs', function (Blueprint $table) {
            $table->integer('cancellation_request_mapping_id', true);
            $table->integer('id')->default(0);
            $table->string('document_type', 50)->nullable();
            $table->string('file', 50)->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cancellation_request_mapping_docs');
    }
}
