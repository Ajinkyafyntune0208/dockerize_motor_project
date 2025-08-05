<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColmS2stable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('server_to_server_req_response', 'system_response')) {
            Schema::table('server_to_server_req_response', function (Blueprint $table) {
                $table->string('system_response')->after('response')->nullable();
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
        if (Schema::hasColumn('server_to_server_req_response', 'system_response')) {
            Schema::table('server_to_server_req_response', function (Blueprint $table) {
                $table->dropColumn('system_response');
            });
        }
    }
}
