<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInPolicyDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_details', function (Blueprint $table) {
            if(!Schema::hasColumn('policy_details', 'rehit_source')) {
                $table->string('rehit_source',20)->nullable()->after('status');
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
        Schema::table('policy_details', function (Blueprint $table) {
            if(Schema::hasColumn('policy_details', 'rehit_source')) {
                $table->dropColumn('rehit_source');
            }
        });
    }
}
