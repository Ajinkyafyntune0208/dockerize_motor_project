<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGstCessCalculationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gst_cess_calculation', function (Blueprint $table) {
            $table->integer('gst_id', true);
            $table->string('producer_state', 20);
            $table->string('consumer_state', 20);
            $table->integer('igst_precent');
            $table->integer('cgst_precent');
            $table->integer('sgst_precent');
            $table->integer('created_by');
            $table->dateTime('created_date');
            $table->integer('updated_by');
            $table->dateTime('updated_date');
            $table->integer('deleted_by');
            $table->dateTime('deleted_date');
            $table->string('status')->default('Active');
            $table->date('effective_from');
            $table->date('effective_to');
            $table->string('tax1_name', 50);
            $table->string('tax2_name', 50);
            $table->string('tax3_name', 50);
            $table->string('tax1_value', 50);
            $table->string('tax2_value', 50);
            $table->string('tax3_value', 50);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gst_cess_calculation');
    }
}
