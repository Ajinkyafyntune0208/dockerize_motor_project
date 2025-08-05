<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddedNewColumnInUserProposalTableTpInsuranceCompanyName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->text('tp_insurance_company_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('user_proposal', 'tp_insurance_company_name')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->dropColumn('tp_insurance_company_name');
            });
        }
    }
}
