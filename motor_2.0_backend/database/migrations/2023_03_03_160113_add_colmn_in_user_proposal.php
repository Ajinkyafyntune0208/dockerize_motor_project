<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColmnInUserProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            if(!Schema::hasColumn('user_proposal', 'totalTpPremium')) {
                $table->string('totalTpPremium',20)->nullable()->after('cpa_start_date');
            }
            if(!Schema::hasColumn('user_proposal', 'fullName'))
            {
                $table->text('fullName')->nullable()->after('tp_insurance_company_name');
            }
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
            if(Schema::hasColumn('user_proposal', 'totalTpPremium') && Schema::hasColumn('user_proposal', 'fullName')) {
                $table->dropColumn('totalTpPremium');
                $table->dropColumn('fullName');
            }
        });
    }
}
