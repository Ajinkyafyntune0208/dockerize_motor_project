<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOdTenureColumnInUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->text('cpa_tenure')->nullable();
            $table->text('tp_tenure')->nullable();
            $table->text('od_tenure')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->dropColumn(['cpa_tenure', 'tp_tenure', 'od_tenure']);
        });
    }
}
