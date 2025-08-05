<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexColumnToChassisNumberAndEngineNumber extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('user_proposal')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->fullText('chassis_number');
                $table->fullText('engine_number');
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
        if (Schema::hasTable('user_proposal')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->removeIndex('chassis_number');
                $table->removeIndex('engine_number');
            });
        }
    }
}
