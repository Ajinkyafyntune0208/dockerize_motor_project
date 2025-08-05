<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsInUserProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->string('state_id')->nullable();
            $table->string('city_id')->nullable();
            $table->string('nominee_dob')->nullable();
            $table->string('car_registration_state_id')->nullable();
            $table->string('car_registration_city_id')->nullable();
            $table->string('insurance_company_name')->nullable();
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
            $table->dropColumn('state_id');
            $table->dropColumn('city_id');
            $table->dropColumn('nominee_dob');
            $table->dropColumn('car_registration_state_id');
            $table->dropColumn('car_registration_city_id');
            $table->dropColumn('insurance_company_name');
        });
    }
}
