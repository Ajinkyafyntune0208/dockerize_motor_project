<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfigureReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configure_report', function (Blueprint $table) {
            $table->integer('configure_report_id', true);
            $table->integer('corporate_client_id')->nullable();
            $table->string('configure_report_name', 500)->nullable();
            $table->string('status')->default('Active');
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('configure_report');
    }
}
