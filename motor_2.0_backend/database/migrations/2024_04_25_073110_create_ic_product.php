<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ic_product')){
            Schema::create('ic_product', function (Blueprint $table) {
                $table->id('ic_policy_id');
                $table->string('product_sub_type_id')->nullable();
                $table->string('insurance_company_id')->nullable();
                $table->string('premium_type_id')->nullable();
                $table->set('business_type', ['newbusiness','rollover','breakin'])->nullable();
                $table->string('product_unique_name')->nullable();
                $table->string('product_name')->nullable();
                $table->string('product_identifier')->nullable();
                $table->string('default_discount')->nullable();
                $table->string('pos_flag')->nullable();
                $table->string('owner_type')->nullable();
                $table->string('gcv_carrier_type')->nullable();
                $table->string('zero_dep')->nullable();
                $table->string('good_driver_discount')->nullable();
                $table->string('tenure')->nullable();
                $table->string('product_key')->nullable();
                $table->string('consider_for_visibility_report')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ic_product');
    }
};
