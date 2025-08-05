<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubSourceColumnInUserProductJourneyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_product_journey', function (Blueprint $table) {
            $table->string('sub_source', 30)->nullable();
            $table->string('campaign_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_product_journey_id', function (Blueprint $table) {
            $table->dropColumn('sub_source');
            $table->dropColumn('campaign_id');
        });
    }
}
