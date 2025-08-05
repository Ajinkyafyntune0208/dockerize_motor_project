<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveColumnsFromTableMasterPolicy extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_policy', function (Blueprint $table) {
            $table->dropColumn('policy_no');
            $table->dropColumn('corp_client_id');
            $table->dropColumn('policy_start_date');
            $table->dropColumn('policy_end_date');
            $table->dropColumn('endorsement_no');
            $table->dropColumn('endorsement_effective_date');
            $table->dropColumn('sum_insured');
            $table->dropColumn('premium');
            $table->dropColumn('created_by');
            $table->dropColumn('updated_by');
            $table->dropColumn('deleted_date');
            $table->dropColumn('predefine_series');
            $table->dropColumn('start_range');
            $table->dropColumn('end_range');
            $table->dropColumn('last_issued_policynumber');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_policy', function (Blueprint $table) {
            //
        });
    }
}
