<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestPremiumCalculateJivanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('test_premium_calculate_jivan', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id')->default(0);
            $table->integer('excel_bucket_id')->default(0);
            $table->string('corporate_client', 50)->default('0');
            $table->string('master_policy', 50)->default('0');
            $table->string('insurance_company', 50)->default('0');
            $table->string('manufacture_year', 50)->default('0');
            $table->date('policy_start_date')->nullable();
            $table->date('policy_expiry_date')->nullable();
            $table->string('vehicle_usage', 50)->default('0');
            $table->string('manufacturer', 50)->default('0');
            $table->string('model', 50)->default('0');
            $table->string('version', 50)->default('0');
            $table->string('rto', 50)->default('0');
            $table->string('ex_showroom_price_idv', 50)->default('0');
            $table->string('proposal_type', 50)->default('0');
            $table->string('total_electric', 50)->default('0');
            $table->string('total_non_electric', 50)->default('0');
            $table->string('voluntary_excess', 50)->default('0');
            $table->string('anti_theft_device', 50)->default('0');
            $table->string('claims_made_in_existing_policy', 50)->default('0');
            $table->string('ncb', 50)->default('0');
            $table->string('pa_cover_unnamed_person', 50)->default('0');
            $table->string('pa_cover_owner_driver', 50)->default('0');
            $table->string('aa_membership', 256)->default('0');
            $table->string('status', 256)->default('0');
            $table->string('excel_bucket_file', 256)->default('0');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('test_premium_calculate_jivan');
    }
}
