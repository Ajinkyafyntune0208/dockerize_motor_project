<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVisibilityReportLogSummaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('visibility_report_log_summary', function (Blueprint $table) {
            $table->id();
            $table->integer('from');
            $table->integer('to');
            $table->timestamp('from_date');
            $table->timestamp('to_date');
            $table->string('method_type', 100);
            $table->json('data');
            $table->timestamps();
            $table->index('from');
            $table->index('to');
            $table->index('method_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('visibility_report_log_summary');
    }
}
