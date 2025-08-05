<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueIIBIDToUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->string('uniqueiibid', 100)->after('vehicle_usage_type')->nullable();
            $table->enum('iibresponserequired', array('Y','N','NA'))->default('N')
                  ->after('uniqueiibid');
            $table->enum('iibncb', array('true','false'))->default('false')
                  ->after('iibresponserequired');
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
            $table->dropColumn('uniqueiibid');
            $table->dropColumn('iibresponserequired');
            $table->dropColumn('iibncb');
        });
    }
}
