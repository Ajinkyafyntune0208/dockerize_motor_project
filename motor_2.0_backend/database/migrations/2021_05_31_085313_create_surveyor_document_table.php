<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyorDocumentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveyor_document', function (Blueprint $table) {
            $table->integer('surveyor_document_id', true);
            $table->integer('proposal_id');
            $table->integer('surveyor_report_id')->nullable();
            $table->string('upload_file_name', 200)->nullable();
            $table->string('document_type', 20)->nullable();
            $table->string('document_summary', 5000)->nullable();
            $table->string('document_size', 20)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_date');
            $table->dateTime('updated_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('surveyor_document');
    }
}
