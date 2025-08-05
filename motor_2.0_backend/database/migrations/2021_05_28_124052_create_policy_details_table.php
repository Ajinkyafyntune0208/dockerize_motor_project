<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_details', function (Blueprint $table) {
            $table->bigInteger('policy_id', true);
            $table->bigInteger('proposal_id')->nullable();
            $table->string('policy_number', 50)->nullable();
            $table->string('idv', 50)->nullable();
            $table->string('policy_start_date', 50)->nullable();
            $table->integer('ncb')->nullable();
            $table->integer('premium')->nullable();
            $table->text('pdf_url')->nullable();
            $table->string('status')->nullable()->default('Y');
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
        Schema::dropIfExists('policy_details');
    }
}
