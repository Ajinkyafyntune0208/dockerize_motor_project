<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUrlColumnInApiTimeoutAutoScalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('api_timeout_auto_scales', function (Blueprint $table) {
            $table->text('endpoint_url')->after('unique_record')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('api_timeout_auto_scales', 'endpoint_url')) {
            Schema::table('api_timeout_auto_scales', function (Blueprint $table) {
                $table->dropColumn('endpoint_url');
            });
        }
    }
}
