<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexInRenewalDataApiTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        if (Schema::hasTable('renewal_data_api')) {
            Schema::table('renewal_data_api', function (Blueprint $table) {
                $table->index('user_product_journey_id');
                $table->index('registration_no');
                $table->index('policy_number');
                $table->index('mmv_source');
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
        if (Schema::hasTable('renewal_data_api')) {
            Schema::table('renewal_data_api', function (Blueprint $table) {
                $table->removeIndex('user_product_journey_id');
                $table->removeIndex('registration_no');
                $table->removeIndex('policy_number');
                $table->removeIndex('mmv_source');
            });
        }
    }
}
