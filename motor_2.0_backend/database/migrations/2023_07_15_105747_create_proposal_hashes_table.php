<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalHashesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_hashes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_product_journey_id')->unsigned()->nullable();
            $table->bigInteger('user_proposal_id')->unsigned()->nullable();
            $table->string('hash')->nullable();
            $table->longText('additional_details_data')->nullable();
            $table->index('user_product_journey_id');
            $table->index('user_proposal_id');
            $table->index('hash');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal_hashes');
    }
}
