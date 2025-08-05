<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClaimsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('policy_number', 250)->default('');
            $table->integer('user_id')->nullable();
            $table->integer('no_of_occupants')->nullable();
            $table->string('incident_remarks', 100)->nullable();
            $table->text('damage_type')->nullable();
            $table->string('police_report', 50)->nullable();
            $table->string('name_of_police_station', 50)->nullable();
            $table->string('place_of_accident', 50)->nullable();
            $table->date('date_of_claim')->nullable();
            $table->integer('incident_id')->nullable();
            $table->integer('total_part_cost')->nullable();
            $table->integer('total_labour_cost')->nullable();
            $table->integer('claim_amount')->nullable();
            $table->integer('claim_settlement_amount')->nullable();
            $table->string('docs', 50)->nullable();
            $table->string('doc_list', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('claim_stage', 50)->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('claims');
    }
}
