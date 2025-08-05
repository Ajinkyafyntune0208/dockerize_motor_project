<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->index('user_product_journey_id');
            $table->foreign('user_product_journey_id')->references('user_product_journey_id')->on('user_product_journey')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            //
        });
    }
}
