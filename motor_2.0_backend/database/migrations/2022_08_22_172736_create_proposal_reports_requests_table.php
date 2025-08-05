<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalReportsRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('proposal_reports_requests'))
        {
            Schema::create('proposal_reports_requests', function (Blueprint $table) {
                $table->id();
                $table->string('from')->nullable();
                $table->string('to')->nullable();
                $table->string('seller_type')->nullable();
                $table->string('seller_id')->nullable();
                $table->string('transaction_stage')->nullable();
                $table->string('product_type')->nullable();
                $table->bigInteger('enquiry_id')->nullable();
                $table->string('proposal_no')->nullable();
                $table->string('policy_no')->nullable();
                $table->string('company_alias')->nullable();
                $table->dateTime('from_time')->nullable();
                $table->dateTime('to_time')->nullable();
                $table->integer('limit')->nullable();
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
        if (Schema::hasTable('proposal_reports_requests'))
        {
            Schema::dropIfExists('proposal_reports_requests');
        }
    }
}
