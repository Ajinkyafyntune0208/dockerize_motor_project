<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorAddonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor_addon', function (Blueprint $table) {
            $table->integer('addon_id', true);
            $table->string('addon_name', 50)->nullable();
            $table->string('addon_code', 50)->nullable();
            $table->string('addon_description', 50)->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->text('addon_title');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_addon');
    }
}
