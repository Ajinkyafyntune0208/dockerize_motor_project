<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCvBreakinStatusTableColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_breakin_status', function (Blueprint $table) {
            $table->bigInteger('user_proposal_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cv_breakin_status', function (Blueprint $table) {
            $table->string('user_proposal_id')->nullable()->change();
        });
    }
}
