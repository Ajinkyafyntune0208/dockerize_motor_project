<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNewIndiaColorMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('new_india_color_masters', function (Blueprint $table) {
            $table->id();
            $table->string('color_code');
            $table->string('color');
        });

        if (  Schema::hasTable('new_india_color_masters'))
        {
            Artisan::call('db:seed', [
                '--class' => 'newindiacolor',
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
        Schema::dropIfExists('new_india_color_masters');
    }
}
