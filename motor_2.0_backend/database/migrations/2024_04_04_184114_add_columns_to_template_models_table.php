<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToTemplateModelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('template_models', function (Blueprint $table) {
            $table->string('subject');
            $table->string('to');
            $table->string('bcc');
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
            $table->dropColumn('subject');
            $table->dropColumn('to');
            $table->dropColumn('bcc');
        });
    }
}
