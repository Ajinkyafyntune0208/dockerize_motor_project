<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TemplateModels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('template_models', function (Blueprint $table) {
            $table->string('footer')->nullable();
            $table->string('global_header')->nullable();
            $table->string('message_type')->nullable();
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
            $table->dropColumn('footer');
            $table->dropColumn('global_header');
            $table->dropColumn('message_type');
        });
    }
}
