<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTemplateMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('template_models', function (Blueprint $table) {
            $table->string('subject')->nullable()->change();
            $table->string('to')->nullable()->change();
            $table->string('bcc')->nullable()->change();
            $table->string('cc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('template_models', function (Blueprint $table) {
            $table->string('subject')->nullable(false)->change();
            $table->string('to')->nullable(false)->change();
            $table->string('bcc')->nullable(false)->change();
            $table->dropColumn('cc');
        });
    }
}
