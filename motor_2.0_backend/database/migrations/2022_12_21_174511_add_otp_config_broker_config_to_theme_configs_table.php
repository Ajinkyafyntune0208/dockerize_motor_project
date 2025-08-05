<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtpConfigBrokerConfigToThemeConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('theme_configs', function (Blueprint $table) {
            $table->json('otp_config')->nullable()->after('theme_config');
            $table->json('broker_config')->nullable()->after('theme_config');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('theme_configs', function (Blueprint $table) {
            $table->dropColumn('otp_config');
            $table->dropColumn('broker_config');
        });
    }
}
