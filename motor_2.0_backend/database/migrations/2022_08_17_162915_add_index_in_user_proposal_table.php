<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexInUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->index('proposal_no');
            $table->string('vehicale_registration_number',20)->change()->index();
            $table->index('mobile_number');
            $table->index('email');
            $table->index('created_date');
        });

        Schema::table('payment_request_response', function (Blueprint $table) {
            $table->index('updated_at');
        });

        Schema::table('cv_journey_stages', function (Blueprint $table) {
            $table->index('updated_at');
            $table->index('stage');
        });

        Schema::table('user_product_journey', function (Blueprint $table) {
            $table->index('corporate_id');
            $table->index('domain_id');
        });

        Schema::table('policy_details', function (Blueprint $table) {
            $table->index('policy_number');
        });

        Schema::table('cv_agent_mappings', function (Blueprint $table) {
            $table->index('agent_id');
        });

        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('registration_details', function (Blueprint $table) {
            $table->index('vehicle_reg_no');
        });
        
        Schema::table('whatsapp_request_responses', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->index('enquiry_id');
            $table->index('mobile_no');
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
            //
        });
    }
}
