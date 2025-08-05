<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToProdTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        // if (!(env('APP_ENV') != 'local' && in_array(config('constants.motorConstant.SMS_FOLDER'), ['ola', 'renewbuy']))) {
            
            Schema::table('ckyc_upload_documents', function (Blueprint $table) {
                $table->index('user_product_journey_id', 'ckyc_upload_documents_user_product_journey_id');
            });
            
            Schema::table('users', function (Blueprint $table) {
                $table->index('mobile_no', 'users_mobile_no');
            });
            
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->fullText('ckyc_reference_id', 'user_proposal_ckyc_reference_id');
            });
            
            Schema::table('renewal_data_api', function (Blueprint $table) {
                $table->index('user_product_journey_id', 'renewal_data_user_product_journey_id');
            });
        // }
    }
    
    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        if (!(env('APP_ENV') != 'local' && in_array(config('constants.motorConstant.SMS_FOLDER'), ['ola', 'renewbuy']))) {
            
            Schema::table('ckyc_upload_documents', function (Blueprint $table) {
                $table->dropIndex('ckyc_upload_documents_user_product_journey_id');
            });
            
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_mobile_no');
            });
            
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->dropIndex('user_proposal_ckyc_reference_id');
            });
            
            Schema::table('renewal_data_api', function (Blueprint $table) {
                $table->dropIndex('renewal_data_user_product_journey_id');
            });
        }
    }
}
