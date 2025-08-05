<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyorClaimMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveyor_claim_mapping', function (Blueprint $table) {
            $table->integer('surveyor_claim_mapping_id', true);
            $table->integer('surveyor_id')->nullable();
            $table->integer('claim_id')->nullable();
            $table->integer('createdby')->nullable();
            $table->string('isactive')->default('Active');
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
        Schema::dropIfExists('surveyor_claim_mapping');
    }
}
