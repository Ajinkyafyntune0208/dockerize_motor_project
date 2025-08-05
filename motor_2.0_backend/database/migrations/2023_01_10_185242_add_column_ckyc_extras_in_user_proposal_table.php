<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCkycExtrasInUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            if ( ! Schema::hasColumn('user_proposal', 'ckyc_extras')) {
                $table->text('ckyc_extras')->nullable();
            }
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
            if (Schema::hasColumn('user_proposal', 'ckyc_extras')) {
                $table->dropColumn('ckyc_extras');
            }
        });
    }
}
