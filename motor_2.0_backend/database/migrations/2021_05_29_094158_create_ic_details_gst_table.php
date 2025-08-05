<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcDetailsGstTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_details_gst', function (Blueprint $table) {
            $table->integer('ic_details_gst_id', true);
            $table->integer('insurance_company_id')->nullable();
            $table->string('branch_name', 50)->nullable();
            $table->string('branch_manager_name', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('contact_no', 50)->nullable();
            $table->integer('pincode')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('state', 50)->nullable();
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
        Schema::dropIfExists('ic_details_gst');
    }
}
