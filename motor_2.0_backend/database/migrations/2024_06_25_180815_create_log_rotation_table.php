<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogRotationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_rotation', function (Blueprint $table) {
            $table->id('log_rotation_id');
            $table->enum('type_of_log', ['file', 'database']);
            $table->string('location')->nullable();
            $table->string('db_table')->nullable();
            $table->integer('backup_data_onwards');
            $table->enum('log_rotation_frequency', ['daily', 'weekly', 'monthly', 'quaterly', 'yearly']);
            $table->integer('log_to_be_retained');
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
        Schema::dropIfExists('log_rotation');
    }
}
