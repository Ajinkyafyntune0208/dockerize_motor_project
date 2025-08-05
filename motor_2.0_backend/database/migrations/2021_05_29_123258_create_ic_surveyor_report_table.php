<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcSurveyorReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_surveyor_report', function (Blueprint $table) {
            $table->integer('ic_surveyor_report_id', true);
            $table->string('ic_surveyor_report_doc_name', 100)->nullable();
            $table->string('surveyor_for', 11)->nullable();
            $table->string('surveyor_doc_shortname', 1000)->nullable();
            $table->integer('ic_id');
            $table->string('status', 10)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_date')->nullable();
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
        Schema::dropIfExists('ic_surveyor_report');
    }
}
