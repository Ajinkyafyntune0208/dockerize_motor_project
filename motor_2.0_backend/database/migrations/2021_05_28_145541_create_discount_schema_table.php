<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscountSchemaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discount_schema', function (Blueprint $table) {
            $table->integer('discount_schema_id', true);
            $table->integer('broker_id');
            $table->string('discount_type', 50);
            $table->string('schema_name', 50);
            $table->dateTime('schema_valid_from');
            $table->dateTime('schema_valid_to');
            $table->dateTime('invoice_from');
            $table->dateTime('invoice_to');
            $table->dateTime('previous_policy_expire_from');
            $table->dateTime('previous_policy_expire_to');
            $table->string('applied_on', 50);
            $table->string('search_applied_on', 50);
            $table->string('ic', 50);
            $table->string('policy_type', 50);
            $table->string('applicable_on', 20);
            $table->string('search_applicable_on', 50);
            $table->string('vehicle_type', 50);
            $table->string('imt_23', 10);
            $table->integer('year_of_manf');
            $table->integer('min_discount');
            $table->integer('max_discount');
            $table->integer('min_age');
            $table->integer('max_age');
            $table->integer('brokerage');
            $table->string('tmf_hypothecation', 50);
            $table->integer('no_of_policy');
            $table->string('status')->default('Active');
            $table->string('upload_document', 50);
            $table->string('is_applicable_on_3_year_policy', 10);
            $table->string('is_ho_approval_required', 10);
            $table->integer('created_by');
            $table->dateTime('created_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discount_schema');
    }
}
