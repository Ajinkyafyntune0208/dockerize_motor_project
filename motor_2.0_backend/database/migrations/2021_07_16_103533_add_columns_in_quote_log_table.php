<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsInQuoteLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quote_log', function (Blueprint $table) {
            $table->string('ic_ids')->nullable();
            $table->unsignedBigInteger('ic_alias')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quote_log', function (Blueprint $table) {
            $table->dropColumn('ic_ids');
            $table->dropColumn('ic_alias');
        });
    }
}
