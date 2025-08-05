<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMisUserDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mis_user_details', function (Blueprint $table) {
            $table->integer('mis_user_id', true);
            $table->string('customer_name', 500)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('product', 50)->nullable();
            $table->string('policy_no', 500)->nullable();
            $table->string('vehicle_reg_no', 100)->nullable();
            $table->string('policy_amount', 100)->nullable();
            $table->string('status', 50)->nullable();
            $table->date('policy_start_date')->nullable();
            $table->date('policy_end_date')->nullable();
            $table->date('previous_policy_expiry_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mis_user_details');
    }
}
