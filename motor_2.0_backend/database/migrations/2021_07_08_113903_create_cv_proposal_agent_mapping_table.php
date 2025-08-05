<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvProposalAgentMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_proposal_agent_mapping', function (Blueprint $table) {
            $table->increments('cv_proposal_agent_mapping_id');
            $table->integer('user_product_journey_id')->nullable();
            $table->integer('user_proposal_id')->nullable();
            $table->unsignedInteger('ic_id')->nullable()->index('FK_cv_proposal_agent_mapping_master_company');
            $table->string('ic_name', 50)->nullable();
            $table->enum('seller_type', ['E', 'P'])->nullable();
            $table->integer('agent_id')->nullable();
            $table->string('agent_name', 100)->nullable();
            $table->string('agent_mobile', 50)->nullable();
            $table->string('agent_email', 100)->nullable();
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
        Schema::dropIfExists('cv_proposal_agent_mapping');
    }
}
