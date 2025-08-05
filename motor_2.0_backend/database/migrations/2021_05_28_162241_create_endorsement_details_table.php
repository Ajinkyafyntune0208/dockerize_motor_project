<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEndorsementDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('endorsement_details', function (Blueprint $table) {
            $table->bigInteger('endorsement_id', true);
            $table->string('policy_number', 50)->nullable();
            $table->integer('endorsement_type_id')->nullable();
            $table->string('registration_id')->nullable();
            $table->string('correct_value', 50)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->string('endorsement_cerificate', 250)->nullable();
            $table->string('attachment', 250)->nullable();
            $table->string('name', 250)->nullable();
            $table->string('tmp_name', 250)->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('endorsement_details');
    }
}
