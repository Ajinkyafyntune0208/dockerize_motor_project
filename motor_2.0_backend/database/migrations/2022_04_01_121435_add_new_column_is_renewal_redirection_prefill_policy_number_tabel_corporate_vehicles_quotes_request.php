<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewColumnIsRenewalRedirectionPrefillPolicyNumberTabelCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'is_renewal_redirection'))
        {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->char('is_renewal_redirection',100)->default('N');
            });            
        } 
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'prefill_policy_number'))
        {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->text('prefill_policy_number')->nullable();
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
        //
    }
}
