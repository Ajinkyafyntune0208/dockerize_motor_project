<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserProductJourneyIdColumnInUserTokenRequestResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_token_request_responses', function (Blueprint $table) {
            $table->unsignedBigInteger('user_product_journey_id')->nullable();
            $table->string('url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_token_request_responses', function (Blueprint $table) {
            $table->dropColumn('user_product_journey_id');
            $table->dropColumn('url');
        });
    }
}
