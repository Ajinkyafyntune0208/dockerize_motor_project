<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogConfiguratorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_configurator', function (Blueprint $table) {
            $table->id('log_configurator_id');
            $table->string('type_of_log')->nullable();
            $table->text('location_path')->nullable();
            $table->text('database_table')->nullable();
            $table->string('backup_onward')->nullable();
            $table->string('log_rotation_frequency')->nullable();
            $table->string('log_to_retained')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('log_configurator');
    }
}
