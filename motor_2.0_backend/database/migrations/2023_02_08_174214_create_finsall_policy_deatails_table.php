<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinsallPolicyDeatailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finsall_policy_deatails', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id');
            $table->string('section', 10)->nullable();
            $table->string('company_allias', 20)->nullable();
            $table->string('proposal_no', 100);
            $table->string('policy_no', 100)->nullable();
            $table->enum('is_payment_finsall', ['Y','N'])->default('N');
            $table->enum('status', ['Redirected to Finsall', 'Payment Success', 'Payment Failure'])->default('Redirected to Finsall');
            $table->text('message')->nullable();
            $table->text('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
}
