<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTemplateMasterOldTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('template_master_old', function (Blueprint $table) {
            $table->integer('temp_id', true);
            $table->string('template_code', 50);
            $table->string('temp_name', 100);
            $table->string('module_name');
            $table->text('template');
            $table->string('template_description', 100);
            $table->dateTime('validity_from_date');
            $table->dateTime('validity_to_date');
            $table->integer('delete_status');
            $table->integer('status');
            $table->integer('created_date');
            $table->integer('modified_date');
            $table->integer('created_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('template_master_old');
    }
}
