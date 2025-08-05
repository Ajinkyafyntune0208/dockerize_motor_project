<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddingColumnsInUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->string('business_type')->nullable();
            $table->string('product_type')->nullable();
            $table->string('ic_name')->nullable();
            $table->string('ic_id')->nullable();
            $table->string('idv')->nullable();
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
            $table->dropColumn('business_type');
            $table->dropColumn('product_type');
            $table->dropColumn('ic_name');
            $table->dropColumn('ic_id');
            $table->dropColumn('idv');
        });
    }
}
