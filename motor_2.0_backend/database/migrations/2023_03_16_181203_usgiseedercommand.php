<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class Usgiseedercommand extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (  Schema::hasTable('usgi_color_masters'))
        {
            Artisan::call('db:seed', [
                '--class' => 'usgiColor',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Artisan::call('db:seed', [
            '--class' => 'usgiColor',
            '--force' => true, // Rollback the seeder forcefully
        ]);
    }
}
