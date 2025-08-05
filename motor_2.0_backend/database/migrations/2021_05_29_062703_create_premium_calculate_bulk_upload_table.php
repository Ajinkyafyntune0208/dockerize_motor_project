<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremiumCalculateBulkUploadTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('premium_calculate_bulk_upload', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id')->default(0);
            $table->integer('product_sub_type_id')->nullable();
            $table->string('excel_bucket_id', 50)->default('0');
            $table->string('corporate_client', 50);
            $table->bigInteger('fk_quote_id')->nullable();
            $table->string('master_policy', 50);
            $table->string('insurance_company', 50);
            $table->integer('product_type_id')->nullable();
            $table->string('policy_type', 50);
            $table->integer('policy_type_id')->nullable();
            $table->string('cover_type', 50);
            $table->string('manufacture_year', 50);
            $table->date('policy_start_date')->nullable();
            $table->date('policy_expiry_date')->nullable();
            $table->string('vehicle_usage', 50);
            $table->integer('vehicle_usage_id')->nullable();
            $table->string('manufacturer', 50);
            $table->string('model', 50);
            $table->string('version', 50);
            $table->string('chassis_no', 50);
            $table->string('engine_no', 50);
            $table->string('previous_policy_number', 50);
            $table->string('rto', 50);
            $table->string('ex_showroom_price_idv', 50);
            $table->string('proposal_type', 50);
            $table->integer('proposal_type_id')->nullable();
            $table->string('fuel_type', 50);
            $table->string('bi_fuel', 50)->nullable();
            $table->string('total_electric', 50);
            $table->string('total_non_electric', 50);
            $table->string('voluntary_excess', 50);
            $table->string('anti_theft_device', 50);
            $table->string('claims_made_in_existing_policy', 50);
            $table->string('ncb', 50)->nullable();
            $table->string('pa_cover_unnamed_person', 50);
            $table->string('pa_cover_owner_driver', 50);
            $table->string('aa_membership', 20);
            $table->string('hypothecation', 10);
            $table->string('hypothecation_name', 20);
            $table->string('vehicle_registration_no', 50);
            $table->string('addoncover', 50)->nullable();
            $table->string('status')->default('Active');
            $table->integer('quote_log_id')->nullable();
            $table->string('excel_bucket_file', 256)->nullable();
            $table->string('zero_depreciation', 256)->nullable();
            $table->string('road_side_assistance', 256)->nullable();
            $table->string('engine_protector', 256)->nullable();
            $table->string('ncb_protection', 256)->nullable();
            $table->string('first_name', 30)->nullable();
            $table->string('middle_name', 20)->nullable();
            $table->string('last_name', 20)->nullable();
            $table->string('email', 20)->nullable();
            $table->string('mobile', 15)->nullable();
            $table->string('city', 15)->nullable();
            $table->string('state', 20)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('address1', 500)->nullable();
            $table->string('address2', 500)->nullable();
            $table->string('address3', 500)->nullable();
            $table->string('prev_insurance_company', 20)->nullable();
            $table->string('vehicle_registration_type', 20)->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable()->useCurrent();
            $table->string('is_proposal_submit', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('premium_calculate_bulk_upload');
    }
}
