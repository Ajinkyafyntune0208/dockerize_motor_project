<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToPremCalcPlaceholdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
            //
            Schema::table('prem_calc_placeholders', function (Blueprint $table) {
                $table->enum('placeholder_type', ['user', 'system'])
                      ->default('user')
                      ->after('placeholder_value');
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prem_calc_placeholders', function (Blueprint $table) {
            //
        });
    }
}
