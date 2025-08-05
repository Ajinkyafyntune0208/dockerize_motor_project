<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_proposal', function (Blueprint $table) {
            $table->integer('user_proposal_id')->autoIncrement();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->integer('fk_proposal_detail_id')->default(0);
            $table->integer('fk_quote_id')->nullable();
            $table->string('title')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('office_email')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('mobile_number', 15)->nullable();
            $table->string('dob', 50)->nullable();
            $table->string('occupation', 50)->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('pan_number', 50)->nullable();
            $table->string('gst_number', 50)->nullable();
            $table->text('address_line1')->nullable();
            $table->text('address_line2')->nullable();
            $table->text('address_line3')->nullable();
            $table->integer('pincode')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('city', 50)->nullable();
            $table->text('street')->nullable();
            $table->text('rto_location')->nullable();
            $table->text('vehicle_color')->nullable();
            $table->text('is_valid_puc')->nullable();
            $table->text('financer_agreement_type')->nullable();
            $table->text('financer_location')->nullable();
            $table->boolean('is_car_registration_address_same')->nullable();
            $table->text('car_registration_address1')->nullable();
            $table->text('car_registration_address2')->nullable();
            $table->text('car_registration_address3')->nullable();
            $table->text('car_registration_pincode')->nullable();
            $table->text('car_registration_state')->nullable();
            $table->text('car_registration_city')->nullable();
            $table->text('vehicale_registration_number')->nullable();
            $table->string('vehicle_manf_year', 50)->nullable();
            $table->text('engine_number')->nullable();
            $table->text('chassis_number')->nullable();
            $table->text('is_vehicle_finance')->nullable();
            $table->text('name_of_financer')->nullable();
            $table->string('hypothecation_city', 100)->nullable();
            $table->text('previous_insurance_company')->nullable();
            $table->text('previous_policy_number')->nullable();
            $table->text('previous_insurer_pin')->nullable();
            $table->text('previous_insurer_address')->nullable();
            $table->text('nominee_name')->nullable();
            $table->text('nominee_age')->nullable();
            $table->text('nominee_relationship')->nullable();
            $table->datetime('proposal_date')->nullable()->useCurrent();
            $table->string('premium_paid_by', 50)->nullable();
            $table->string('status')->nullable()->default("Active");
            $table->string('vehicle_registration_no', 50)->nullable();
            $table->string('engine_no', 50)->nullable();
            $table->string('chassis_no', 50)->nullable();
            $table->string('final_premium', 50)->nullable();
            $table->integer('premium_bulk_upload_id')->nullable();
            $table->string('policy_start_date')->nullable();
            $table->string('policy_end_date')->nullable();
            $table->string('policy_no', 265)->nullable();
            $table->string('is_policy_issued', 500)->default('Pending');
            $table->string('policy_remark', 500)->nullable();
            $table->string('policy_copy', 500)->nullable();
            $table->integer('user_for')->nullable();
            $table->integer('user_type_id')->nullable();
            $table->integer('ic_user_for')->nullable();
            $table->integer('ic_user_type_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->datetime('created_date')->useCurrent();
            $table->integer('updated_by')->nullable();
            $table->datetime('updated_date')->nullable();
            $table->string('is_proposal_verifed')->nullable()->default('Yes');
            $table->integer('entry_from')->nullable();
            $table->string('64vb_verified')->nullable()->default('Pending');
            $table->string('remark', 256)->nullable();
            $table->string('prev_policy_expiry_date')->nullable();
            $table->string('is_breakin_case', 11)->nullable();
            $table->string('policy_type', 10)->nullable();
            $table->string('surveyor_status', 100)->nullable();
            $table->string('proposal_no', 200)->nullable();
            $table->string('pol_sys_id', 100)->nullable();
            $table->text('proposal_stage')->nullable();
            $table->text('payment_url')->nullable();
            $table->string('od_premium')->nullable();
            $table->string('tp_premium')->nullable();
            $table->string('ncb_discount')->nullable();
            $table->string('electrical_accessories')->nullable();
            $table->string('non_electrical_accessories')->nullable();
            $table->string('addon_premium')->nullable();
            $table->string('total_premium')->nullable();
            $table->string('service_tax_amount')->nullable();
            $table->string('final_payable_amount')->nullable();
            $table->longText('additional_details')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('unique_proposal_id')->nullable();
            $table->string('version_no')->nullable();
            $table->integer('vehicle_category')->nullable();
            $table->integer('vehicle_usage_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_proposal');
    }
}
