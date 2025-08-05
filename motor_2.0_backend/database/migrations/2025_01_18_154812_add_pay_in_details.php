<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayInDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('premium_details', function (Blueprint $table) {
            $table->bigInteger('payin_conf_id')->nullable()->after('commission_details');
            $table->longText('payin_details')->nullable()->after('commission_details');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('premium_details', function (Blueprint $table) {
            //
        });
    }
}
