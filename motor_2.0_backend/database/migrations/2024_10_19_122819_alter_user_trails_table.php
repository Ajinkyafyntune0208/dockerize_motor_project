<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUserTrailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('user_trails', function (Blueprint $table) {
        //     $table->longText('url')->change();
        // });

        if (Schema::hasTable('user_trails')) {
            if (Schema::hasColumn('user_trails', 'url')) {
                Schema::table('user_trails', function (Blueprint $table) {
                    $table->longText('url')->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
