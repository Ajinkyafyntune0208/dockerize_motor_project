<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumsInUserProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->text('cpa_ins_comp')->nullable();
            $table->text('cpa_policy_fm_dt')->nullable();
            $table->text('cpa_policy_no')->nullable();
            $table->text('cpa_policy_to_dt')->nullable();
            $table->text('cpa_sum_insured')->nullable();
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
            $table->dropColumn('cpa_ins_comp');
            $table->dropColumn('cpa_policy_fm_dt');
            $table->dropColumn('cpa_policy_no');
            $table->dropColumn('cpa_policy_to_dt');
            $table->dropColumn('cpa_sum_insured');
        });
    }
}
