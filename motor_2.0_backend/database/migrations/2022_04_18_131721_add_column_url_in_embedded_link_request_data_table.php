<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnUrlInEmbeddedLinkRequestDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasColumn('embedded_link_request_data', 'url'))
        {
            Schema::table('embedded_link_request_data', function (Blueprint $table) {
                $table->string('url')->nullable();
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
        Schema::table('embedded_link_request_data', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
}
