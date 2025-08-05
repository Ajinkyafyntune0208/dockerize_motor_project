<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposerCkycDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('proposer_ckyc_details')) {
            Schema::create('proposer_ckyc_details', function (Blueprint $table) {
                $table->id();
                $table->integer('user_proposal_id')->nullable()->index();
                $table->bigInteger('user_product_journey_id')->nullable()->index();
                $table->string('related_person_name', 255)->nullable();
                $table->string('relationship_type', 255)->nullable();
                $table->string('organization_type', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposer_ckyc_details');
    }
}
