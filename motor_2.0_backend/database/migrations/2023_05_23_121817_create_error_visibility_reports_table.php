<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateErrorVisibilityReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('error_visibility_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('total');
            $table->integer('success');
            $table->integer('failure');
            $table->string('company', 20);
            $table->string('transaction_type', 20);
            $table->timestamp('report_date');
            $table->timestamps();
            $table->index('report_date');
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('error_visibility_reports');
    }
}
