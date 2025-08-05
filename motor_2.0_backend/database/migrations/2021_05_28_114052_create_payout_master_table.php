<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payout_master', function (Blueprint $table) {
            $table->integer('payout_id', true);
            $table->integer('corp_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('product_sub_type_id')->nullable();
            $table->integer('vehicle_min_age')->nullable();
            $table->integer('vehicle_max_age')->nullable();
            $table->integer('vehicle_min_cc')->nullable();
            $table->integer('vehicle_max_cc')->nullable();
            $table->integer('payout_head_id')->nullable();
            $table->string('payout_head_type', 100)->nullable();
            $table->double('payout_value')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status')->default('Y');
            $table->dateTime('createdon')->useCurrent();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->useCurrent();
            $table->integer('user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payout_master');
    }
}
