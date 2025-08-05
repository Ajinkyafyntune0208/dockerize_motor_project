<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsTouserTable extends Migration
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
                if (!Schema::hasColumn('user', 'otp')) {
            $table->string('otp',6)->nullable();
                }

                if (!Schema::hasColumn('user', 'confirm_otp')) {
            $table->enum('confirm_otp',[1,0])->default(1);
                }

                if (!Schema::hasColumn('user', 'otp_expires_in')) {
            $table->integer('otp_expires_in')->default(3)->nullable();
                }

                if (!Schema::hasColumn('user', 'otp_expires_at')) {
            $table->timestamp('otp_expires_at')->nullable();
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
                if (Schema::hasColumn('user', 'otp')) {
            $table->dropColumn('otp');
                }

                if (Schema::hasColumn('user', 'confirm_otp')) {
            $table->dropColumn('confirm_otp');
                }

                if (Schema::hasColumn('user', 'otp_expires_in')) {
            $table->dropColumn('otp_expires_in');
                }

                if (Schema::hasColumn('user', 'otp_expires_at')) {
            $table->dropColumn('otp_expires_at');
                }
            });
        }
    }
}
