<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_details', function (Blueprint $table) {
            $table->integer('proposal_detail_id', true);
            $table->string('vehicle_registration_no', 50)->nullable();
            $table->string('engine_no', 50)->nullable();
            $table->string('chassis_no', 50)->nullable();
            $table->string('final_premium', 50)->nullable();
            $table->integer('premium_bulk_upload_id')->nullable();
            $table->string('policy_start_date', 20)->nullable();
            $table->string('policy_end_date', 20)->nullable();
            $table->string('policy_no', 265)->nullable();
            $table->string('is_policy_issued', 500)->default('Pending');
            $table->string('policy_remark', 500)->nullable();
            $table->string('policy_copy', 500)->nullable();
            $table->string('status')->default('Active');
            $table->integer('user_for')->nullable();
            $table->integer('user_type_id')->nullable();
            $table->integer('ic_user_for')->nullable();
            $table->integer('ic_user_type_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->useCurrent();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->string('is_proposal_verifed')->nullable()->default('yes');
            $table->integer('fk_quote_id')->nullable();
            $table->integer('entry_from')->nullable();
            $table->string('64vb_verified')->nullable()->default('Pending');
            $table->string('remark', 256)->nullable();
            $table->string('prev_policy_expiry_date', 20)->nullable();
            $table->string('is_breakin_case', 11)->nullable();
            $table->string('policy_type', 10)->nullable();
            $table->string('surveyor_status', 100)->nullable();
            $table->string('proposal_no', 200)->nullable();
            $table->string('pol_sys_id', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal_details');
    }
}
