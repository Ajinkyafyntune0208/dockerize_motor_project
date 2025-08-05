<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClaimSettlementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('claim_settlement', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('claim_settlement_id')->nullable();
            $table->string('vehicle_id', 50)->nullable()->default('');
            $table->dateTime('date_settled')->nullable();
            $table->integer('amount_paid')->nullable();
            $table->string('coverage_id', 50)->nullable();
            $table->string('claim_id', 50)->nullable();
            $table->text('cust_id')->nullable();
            $table->integer('createdby')->default(1);
            $table->dateTime('createdon')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('claim_settlement');
    }
}
