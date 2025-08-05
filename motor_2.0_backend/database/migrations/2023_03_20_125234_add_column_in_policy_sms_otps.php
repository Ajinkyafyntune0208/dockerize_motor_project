<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInPolicySmsOtps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('policy_sms_otps', 'count') && !Schema::hasColumn('policy_sms_otps', 'is_expired')) {
            Schema::table('policy_sms_otps', function (Blueprint $table) {
                $table->unsignedTinyInteger('count')->default(0);
                $table->tinyInteger("is_expired")->default(0);
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
        if (Schema::hasColumn('policy_sms_otps', 'count') && Schema::hasColumn('policy_sms_otps', 'is_expired')) {
            Schema::table('policy_sms_otps', function (Blueprint $table) {
                $table->dropColumn('count');
                $table->dropColumn("is_expired");
            });
        }
    }
}
