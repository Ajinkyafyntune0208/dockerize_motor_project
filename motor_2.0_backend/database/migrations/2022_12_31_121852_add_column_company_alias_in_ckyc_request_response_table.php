<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCompanyAliasInCkycRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ckyc_request_responses', function (Blueprint $table) {
            if (Schema::hasColumn('ckyc_request_responses', 'ic_id')) {
                $table->dropColumn('ic_id');
            }

            if ( ! Schema::hasColumn('ckyc_request_responses', 'company_alias')) {
                $table->string('company_alias')->nullable();
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
        Schema::table('ckyc_request_responses', function (Blueprint $table) {
            //
        });
    }
}
