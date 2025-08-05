<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyReportDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_report_data', function (Blueprint $table) {
            $table->id();
            $table->text('request')->nullable();
            $table->text('user_details')->nullable();
            $table->enum('is_dispatched', ['Y', 'N'])->default('N')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policy_report_data');
    }
}
