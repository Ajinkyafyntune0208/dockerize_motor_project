<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalIcSurveyorMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_ic_surveyor_map', function (Blueprint $table) {
            $table->integer('proposal_ic_surveyor_map_id', true);
            $table->integer('selected_ic_user_surveyor');
            $table->integer('surveyor_user_type_id')->nullable();
            $table->integer('surveyor_user_for')->nullable();
            $table->integer('proposal_id');
            $table->integer('assign_by_user_id');
            $table->string('status', 10);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_date');
            $table->dateTime('updated_date')->useCurrent();
            $table->string('self_or_assign', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal_ic_surveyor_map');
    }
}
