<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUserTokenRequestResponsesAddRequestChecksum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasColumn('user_token_request_responses', 'request_checksum')) {
            Schema::table('user_token_request_responses', function (Blueprint $table) {
                $table->text('request_checksum')->nullable();
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
        Schema::table('user_token_request_responses', function (Blueprint $table) {
            $table->dropColumn('request_checksum');
        });
    }
}
