<?php

use Database\Seeders\GodigitKycUrlSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CkycGodigitFailedCasesData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //     
        if (!Schema::hasTable('ckyc_godigit_failed_cases_data')) 
        {
            Schema::create('ckyc_godigit_failed_cases_data', function (Blueprint $table) {
                $table->id();
                $table->biginteger('user_product_journey_id');
                $table->string('policy_no','50');
                $table->string('kyc_url','150');
                $table->string('return_url','150');
                $table->text('post_data');
                $table->string('status','20');
                $table->index(['user_product_journey_id', 'policy_no'],'index_on_idandpolicyno');
            });

            $seeder = new GodigitKycUrlSeeder();
            $seeder->run();
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
        Schema::dropIfExists('ckyc_godigit_failed_cases_data');
    }
}
