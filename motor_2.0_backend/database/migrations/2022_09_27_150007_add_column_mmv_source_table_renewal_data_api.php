<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnMmvSourceTableRenewalDataApi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('renewal_data_api', 'mmv_source')) {
            Schema::table('renewal_data_api', function (Blueprint $table) {
                $table->string('mmv_source', 100)->nullable()->after('url');
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
