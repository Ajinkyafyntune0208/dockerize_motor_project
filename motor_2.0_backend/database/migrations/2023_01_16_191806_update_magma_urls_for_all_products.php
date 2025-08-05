<?php

use Illuminate\Support\Facades\Schema;
use Database\Seeders\MagmaUpdateUatUrl;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateMagmaUrlsForAllProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        if(env('APP_ENV') == 'local')
        {
            $seeder = new MagmaUpdateUatUrl();
            $seeder->run();

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
