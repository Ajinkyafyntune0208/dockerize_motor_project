<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterPolicyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_policy', function (Blueprint $table) {
            $table->integer('policy_id', true);
            $table->string('policy_no', 500)->unique('policy_no');
            $table->integer('corp_client_id')->index('company_id');
            $table->integer('product_sub_type_id')->index('fk_policy_sub_type_id');
            $table->integer('insurance_company_id');
            $table->integer('premium_type_id');
            $table->date('policy_start_date');
            $table->date('policy_end_date');
            $table->integer('endorsement_no')->nullable();
            $table->integer('default_discount')->nullable();
            $table->date('endorsement_effective_date')->nullable();
            $table->string('sum_insured');
            $table->string('premium')->nullable();
            $table->string('status')->default('Active');
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_date');
            $table->dateTime('updated_date')->nullable();
            $table->dateTime('deleted_date')->nullable();
            $table->string('policy_type', 11);
            $table->string('predefine_series', 50)->nullable();
            $table->string('start_range', 50);
            $table->string('end_range', 50);
            $table->string('last_issued_policynumber', 50)->nullable();
            $table->string('is_premium_online', 5)->nullable();
            $table->string('is_proposal_online', 5)->nullable();
            $table->string('is_payment_online', 5)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_policy');
    }
}
