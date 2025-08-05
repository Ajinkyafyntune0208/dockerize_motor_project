<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMisUserCustomReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mis_user_custom_report', function (Blueprint $table) {
            $table->integer('mis_user_custom_report_id', true);
            $table->integer('congiure_report_id')->nullable();
            $table->string('tbl_col_name', 500)->nullable();
            $table->string('display_col_name')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->string('status', 50)->nullable();
            $table->dateTime('created_date')->useCurrent();
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
        Schema::dropIfExists('mis_user_custom_report');
    }
}
