<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVersionCountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('version_counts', function (Blueprint $table) {
            $table->id();
            $table->longText('version');
            $table->string('status', 255)->default('N'); 
            $table->longText('make')->nullable();
            $table->longText('model')->nullable(); 
            $table->longText('variant')->nullable(); 
            $table->string('policy_count')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('version_counts');
    }
}
