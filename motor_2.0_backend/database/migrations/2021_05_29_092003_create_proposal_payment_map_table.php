<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalPaymentMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_payment_map', function (Blueprint $table) {
            $table->integer('proposal_payment_map_id', true);
            $table->integer('premium_bulk_upload_id');
            $table->integer('payment_detail_id');
            $table->integer('proposal_id');
            $table->string('status');
            $table->integer('created_by');
            $table->dateTime('created_date')->useCurrent();
            $table->integer('updated_by');
            $table->dateTime('updated_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal_payment_map');
    }
}
