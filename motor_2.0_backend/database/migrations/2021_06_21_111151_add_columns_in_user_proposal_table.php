<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsInUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->string('is_cpa')->nullable();
            $table->string('cpa')->nullable();
            $table->string('total_discount')->nullable();
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
            $table->dropColumn('is_cpa');
            $table->dropColumn('cpa');
            $table->dropColumn('total_discount');
        });
    }
}
