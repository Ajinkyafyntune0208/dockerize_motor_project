<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyorReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveyor_report', function (Blueprint $table) {
            $table->integer('surveyor_report_id', true);
            $table->integer('proposal_id');
            $table->integer('surveyor_category_id')->nullable();
            $table->string('surveyor_report_summary', 5000)->nullable();
            $table->string('document_status', 11)->nullable();
            $table->string('status', 20)->nullable();
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
        Schema::dropIfExists('surveyor_report');
    }
}
