<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInApiTimeoutAutoScalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('api_timeout_auto_scales', function (Blueprint $table) {
            $table->string('unique_record', 32)->nullable()->after('id');
            $table->index('unique_record');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('api_timeout_auto_scales', function (Blueprint $table) {
            $table->removeColumn('unique_column');
        });
    }
}
