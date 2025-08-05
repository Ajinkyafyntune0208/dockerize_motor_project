<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSyncBrokerageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sync_brokerage_logs', function (Blueprint $table) {
            $table->bigInteger('new_conf_id')->nullable()->after('id');
            $table->bigInteger('old_conf_id')->nullable()->after('id');
            $table->json('old_config')->nullable()->after('id');
            $table->json('new_config')->nullable()->after('id');
            $table->bigInteger('user_product_journey_id')->after('id');
            $table->bigInteger('retrospective_conf_id')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sync_brokerage_logs', function (Blueprint $table) {
            //
        });
    }
}
