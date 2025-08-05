<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameVahanActivityLogToUserActivityLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('vahan_activity_logs')){
        Schema::rename('vahan_activity_logs', 'user_activity_logs');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if(Schema::hasTable('user_activity_logs')){
            Schema::rename('user_activity_logs', 'vahan_activity_logs');
            }
    }
}
