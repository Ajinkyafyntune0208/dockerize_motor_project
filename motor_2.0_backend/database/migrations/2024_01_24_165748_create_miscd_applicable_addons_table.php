<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMiscdApplicableAddonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('miscd_applicable_addons', function (Blueprint $table) {
            $table->id('addon_id');
            $table->string('addon_name')->nullable();
            $table->integer('ic_id')->nullable();
            $table->string('ic_alias')->nullable();
            $table->string('addon_age')->nullable();
            $table->enum('is_applicable',['Y','N'])->nullable()->default('Y');
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
        Schema::dropIfExists('miscd_applicable_addons');
    }
}
