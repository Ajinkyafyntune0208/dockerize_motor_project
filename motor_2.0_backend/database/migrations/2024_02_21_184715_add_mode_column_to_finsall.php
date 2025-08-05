<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddModeColumnToFinsall extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('finsall_policy_deatails')) {
            Schema::table('finsall_policy_deatails', function (Blueprint $table) {
                $table->string('mode')->after('policy_no')->nullable();
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
        if (Schema::hasTable('finsall_policy_deatails')) {
            Schema::table('finsall_policy_deatails', function (Blueprint $table) {
                //
            });
        }
    }
}
