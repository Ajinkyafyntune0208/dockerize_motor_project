<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionTableProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_table_proposal', function (Blueprint $table) {
            $table->integer('transaction_id', true);
            $table->integer('user_for')->nullable();
            $table->integer('user_type_id')->nullable();
            $table->integer('proposal_detail_id')->nullable();
            $table->integer('premium_bulk_upload_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->useCurrent();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->useCurrent();
            $table->string('status')->default('Active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_table_proposal');
    }
}
