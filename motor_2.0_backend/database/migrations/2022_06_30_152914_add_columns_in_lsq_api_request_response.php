<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsInLsqApiRequestResponse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lsq_api_request_responses', function (Blueprint $table) {
            $table->string('rc_number')->nullable();
            $table->integer('previous_ncb')->nullable();
            $table->integer('current_ncb')->nullable();
            $table->string('claim_status')->default('N');
            $table->string('ic_name')->nullable();
            $table->integer('od_premium')->nullable();
            $table->integer('tp_premium')->nullable();
            $table->integer('cpa_premium')->nullable();
            $table->integer('addon_premium')->nullable();
            $table->integer('total_premium')->nullable();
            $table->string('policy_start_date')->nullable();
            $table->string('enquiry_id')->nullable();
            $table->text('enquiry_link')->nullable();
            $table->string('policy_type')->nullable();
            $table->string('policy_tenure')->nullable();
            $table->integer('proposal_id')->nullable();
            $table->string('quote_id')->nullable();
            $table->text('quote_link')->nullable();
            $table->string('owner_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lsq_api_request_responses', function (Blueprint $table) {
            $table->dropColumn('rc_number');
            $table->dropColumn('previous_ncb');
            $table->dropColumn('current_ncb');
            $table->dropColumn('claim_status');
            $table->dropColumn('ic_name');
            $table->dropColumn('od_premium');
            $table->dropColumn('tp_premium');
            $table->dropColumn('cpa_premium');
            $table->dropColumn('addon_premium');
            $table->dropColumn('total_premium');
            $table->dropColumn('policy_start_date');
            $table->dropColumn('enquiry_id');
            $table->dropColumn('enquiry_link');
            $table->dropColumn('policy_type');
            $table->dropColumn('policy_tenure');
            $table->dropColumn('proposal_id');
            $table->dropColumn('quote_id');
            $table->dropColumn('quote_link');
            $table->dropColumn('owner_name');
        });
    }
}
