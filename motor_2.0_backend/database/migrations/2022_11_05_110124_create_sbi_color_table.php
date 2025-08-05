<?php

use Database\Seeders\SbiColorSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSbiColorTable extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sbi_colors', function (Blueprint $table) {
            $table->id();
            $table->string('sbi_color');
            $table->timestamps();
        });
        $seeder = new SbiColorSeeder();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sbi_colors');
    }
}
