<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAgentDiscountColumnToSelectedAddonsColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('selected_addons', function (Blueprint $table) {
            $table->longText('agent_discount')->nullable()->after('discounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('selected_addons', function (Blueprint $table) {
            $table->dropColumn('agent_discount');
        });
    }
}
