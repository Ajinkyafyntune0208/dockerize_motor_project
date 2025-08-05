<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorporateVehiclesQuotesRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corporate_vehicles_quotes_request', function (Blueprint $table) {
            $table->bigInteger('quotes_request_id', true);
            $table->integer('version_id')->nullable();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->enum('policy_type',['comprehensive','own_damage','third_party','short_term'])->nullable();
            $table->enum('business_type',['rollover','newbusiness','breakin','short_term'])->nullable();
            $table->string('vehicle_register_date', 50)->nullable();
            $table->string('vehicle_registration_no', 100)->nullable();
            $table->integer('corp_id')->nullable();
            $table->integer('product_id')->nullable();
            $table->string('previous_policy_expiry_date')->nullable();
            $table->string('previous_policy_type', 50)->nullable();
            $table->string('previous_insurer', 255)->nullable();
            $table->integer('insurance_company_id')->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('manufacture_year', 50)->nullable();
            $table->string('rto_code', 50)->nullable();
            $table->integer('ex_showroom_price_idv')->nullable();
            $table->integer('edit_idv')->default(0);
            $table->integer('edit_od_discount')->nullable()->default(0);
            $table->enum('vehicle_owner_type',['I', 'C'])->nullable();
            $table->integer('electrical_acessories_value')->nullable();
            $table->integer('nonelectrical_acessories_value')->nullable();
            $table->integer('bifuel_kit_value')->nullable();
            $table->enum('is_claim',['Y', 'N'])->default('N');
            $table->string('previous_ncb', 50)->nullable();
            $table->string('applicable_ncb', 50)->nullable();
            $table->integer('voluntary_excess_value')->nullable();
            $table->string('anti_theft_device')->nullable();
            $table->integer('unnamed_person_cover_si')->nullable();
            $table->string('pa_cover_owner_driver', 20)->nullable();
            $table->string('aa_membership')->nullable();
            $table->integer('vehicle_used_for')->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
            $table->string('engine_no', 50)->nullable();
            $table->string('chassis_no', 50)->nullable();
            $table->string('previous_policy_type_id', 10)->nullable();
            $table->string('is_od_discount_applicable')->default('N');
            $table->string('is_prev_zero_dept', 20)->nullable();
            $table->string('change_default_discount', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corporate_vehicles_quotes_request');
    }
}
