<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtpTypeAndTotpSecretToUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (Schema::hasTable('user')) {   
        Schema::table('user', function (Blueprint $table) {
                if (!Schema::hasColumn('user', 'otp_type')) {
            $table->string('otp_type')->nullable();
                }

                if (!Schema::hasColumn('user', 'totp_secret')) {
            $table->string('totp_secret')->nullable();
                }
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
        if (Schema::hasTable('user')) {
            Schema::table('user', function (Blueprint $table) {
                if (Schema::hasColumn('user', 'otp_type')) {
            $table->dropColumn('otp_type');
                }
                
                if (Schema::hasColumn('user', 'totp_secret')) {
            $table->dropColumn('totp_secret');
                }
            });
        }
    }
}