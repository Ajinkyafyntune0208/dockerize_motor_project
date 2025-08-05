<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MultipleStageNameUpdationAndCorrection extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if( Schema::hasTable('finsall_policy_deatails') && Schema::hasColumn('finsall_policy_deatails', 'status'))
        {
            DB::statement("CREATE TABLE finsall_policy_deatails_".date('dmY')." LIKE finsall_policy_deatails");
            DB::statement("INSERT INTO finsall_policy_deatails_".date('dmY')." SELECT * FROM finsall_policy_deatails" );
            DB::statement("ALTER TABLE finsall_policy_deatails MODIFY COLUMN `status` ENUM( 'Redirected to Finsall', 'Payment Success', 'Payment Failed' ) NOT NULL DEFAULT 'Redirected to Finsall'");
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'Payment Initiated' WHERE LOWER( `status` ) = 'payment initiated' ");
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'Payment Failed' WHERE LOWER( `status` ) = 'payment failed' ");
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'Payment Success' WHERE LOWER( `status` ) = 'payment success' "); 
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'Payment Failure' WHERE LOWER( `status` ) = 'payment failure' ");
        }

        if( Schema::hasTable('payment_request_response') && Schema::hasColumn('payment_request_response', 'status'))
        {
            DB::statement("CREATE TABLE payment_request_response_".date('dmY')." LIKE payment_request_response");
            DB::statement("INSERT INTO payment_request_response_".date('dmY')." SELECT * FROM payment_request_response" );
            DB::statement("UPDATE payment_request_response SET `status` = 'Payment Initiated' WHERE LOWER( `status` ) = 'payment initiated' ");
            DB::statement("UPDATE payment_request_response SET `status` = 'Payment Failed' WHERE LOWER( `status` ) = 'payment failed' ");
            DB::statement("UPDATE payment_request_response SET `status` = 'Payment Success' WHERE LOWER( `status` ) = 'payment success' "); 
            DB::statement("UPDATE payment_request_response SET `status` = 'Payment Failure' WHERE LOWER( `status` ) = 'payment failure' ");
        }

        if( Schema::hasTable('cv_breakin_status') && Schema::hasColumn('cv_breakin_status', 'breakin_status')) // No need to include the same changes in down() method
        {
            DB::statement("CREATE TABLE cv_breakin_status_".date('dmY')." LIKE cv_breakin_status");
            DB::statement("INSERT INTO cv_breakin_status_".date('dmY')." SELECT * FROM cv_breakin_status" );
            DB::statement("UPDATE cv_breakin_status SET `breakin_status` = 'Pending from IC' WHERE LOWER( `breakin_status` ) = 'pending from ic' ");
            DB::statement("UPDATE cv_breakin_status SET `breakin_status` = 'Inspection Approved' WHERE LOWER( `breakin_status` ) = 'inspection approved' ");
            DB::statement("UPDATE cv_breakin_status SET `breakin_status` = 'Inspection Rejected' WHERE LOWER( `breakin_status` ) = 'inspection rejected' ");
            DB::statement("UPDATE cv_breakin_status SET `breakin_status` = 'Inspection Pending' WHERE LOWER( `breakin_status` ) = 'inspection pending' ");
        }

        if( Schema::hasTable('cv_breakin_status') && Schema::hasColumn('cv_breakin_status', 'breakin_status_final')) // No need to include the same changes in down() method
        {
            if( !Schema::hasTable('cv_breakin_status_'.date('dmY')) )
            {
                DB::statement("CREATE TABLE cv_breakin_status_".date('dmY')." LIKE cv_breakin_status");
                DB::statement("INSERT INTO cv_breakin_status_".date('dmY')." SELECT * FROM cv_breakin_status" );
            }
            DB::statement("UPDATE cv_breakin_status SET `breakin_status_final` = 'Pending from IC' WHERE LOWER( `breakin_status_final` ) = 'pending from ic' ");
            DB::statement("UPDATE cv_breakin_status SET `breakin_status_final` = 'Inspection Approved' WHERE LOWER( `breakin_status_final` ) = 'inspection approved' ");
            DB::statement("UPDATE cv_breakin_status SET `breakin_status_final` = 'Inspection Rejected' WHERE LOWER( `breakin_status_final` ) = 'inspection rejected' ");
            DB::statement("UPDATE cv_breakin_status SET `breakin_status_final` = 'Inspection Pending' WHERE LOWER( `breakin_status_final` ) = 'inspection pending' ");
        }

        if( Schema::hasTable('cv_journey_stages') && Schema::hasColumn('cv_journey_stages', 'stage'))
        {
            DB::statement("CREATE TABLE cv_journey_stages_".date('dmY')." LIKE cv_journey_stages");
            DB::statement("INSERT INTO cv_journey_stages_".date('dmY')." SELECT * FROM cv_journey_stages" );
            
            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Inspection Accepted' WHERE LOWER( `stage` ) = 'inspection accept' ");
            
            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Inspection Rejected' WHERE LOWER( `stage` ) = 'inspection reject' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Inspection Accepted' WHERE LOWER( `stage` ) = 'inspection accepted' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Inspection Approved' WHERE LOWER( `stage` ) = 'inspection approved' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Inspection Pending' WHERE LOWER( `stage` ) = 'inspection pending' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Inspection Rejected' WHERE LOWER( `stage` ) = 'inspection rejected' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Lead Generation' WHERE LOWER( `stage` ) = 'lead generation' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Payment Failed' WHERE LOWER( `stage` ) = 'payment failed' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Payment Initiated' WHERE LOWER( `stage` ) = 'payment initiated' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Payment Success' WHERE LOWER( `stage` ) = 'payment success' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Policy Issued' WHERE LOWER( `stage` ) = 'policy issued' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Policy Issued' WHERE LOWER( `stage` ) = 'policy issued pdf generated' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Policy Issued, but pdf not generated' WHERE LOWER( `stage` ) = 'policy issued, but pdf not generated' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Proposal Accepted' WHERE LOWER( `stage` ) = 'proposal accepted' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Proposal Drafted' WHERE LOWER( `stage` ) = 'proposal drafted' ");

            DB::statement("UPDATE cv_journey_stages SET `stage` = 'Quote - Buy Now' WHERE LOWER( `stage` ) = 'quote - buy now' ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if( Schema::hasTable('finsall_policy_deatails') && Schema::hasColumn('finsall_policy_deatails', 'status'))
        {
            DB::statement("ALTER TABLE finsall_policy_deatails MODIFY COLUMN `status` ENUM( 'Redirected to Finsall', 'Payment Success', 'Payment Failure' ) NOT NULL DEFAULT 'Redirected to Finsall'");
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'PAYMENT INITIATED' WHERE LOWER( `status` ) = 'payment initiated' ");
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'PAYMENT FAILED' WHERE LOWER( `status` ) = 'payment failed' ");
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'PAYMENT SUCCESS' WHERE LOWER( `status` ) = 'payment success' ");
            DB::statement("UPDATE finsall_policy_deatails SET `status` = 'PAYMENT FAILURE' WHERE LOWER( `status` ) = 'payment failure' ");
        }

        if( Schema::hasTable('payment_request_response') && Schema::hasColumn('payment_request_response', 'status'))
        {
            DB::statement("UPDATE payment_request_response SET `status` = 'PAYMENT INITIATED' WHERE LOWER( `status` ) = 'payment initiated' ");
            DB::statement("UPDATE payment_request_response SET `status` = 'PAYMENT FAILED' WHERE LOWER( `status` ) = 'payment failed' ");
            DB::statement("UPDATE payment_request_response SET `status` = 'PAYMENT SUCCESS' WHERE LOWER( `status` ) = 'payment success' "); 
            DB::statement("UPDATE payment_request_response SET `status` = 'PAYMENT FAILURE' WHERE LOWER( `status` ) = 'payment failure' ");
        }
    }
}