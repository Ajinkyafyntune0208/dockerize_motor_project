<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddonApplyOnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addon_apply_on', function (Blueprint $table) {
            $table->integer('addon_apply_on_id', true);
            $table->string('apply_name', 50)->nullable();
            $table->string('status')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('addon_apply_on');
    }
}
