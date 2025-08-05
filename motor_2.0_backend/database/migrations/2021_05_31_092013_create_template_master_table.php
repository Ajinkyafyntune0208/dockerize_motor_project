<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTemplateMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('template_master', function (Blueprint $table) {
            $table->integer('temp_id', true);
            $table->string('template_code', 50);
            $table->string('temp_name', 100);
            $table->string('module_name');
            $table->text('template');
            $table->string('template_description', 100);
            $table->string('validity_from_date', 100)->nullable();
            $table->string('validity_to_date', 100)->nullable();
            $table->integer('delete_status')->nullable();
            $table->integer('status');
            $table->string('created_date', 100)->nullable();
            $table->string('modified_date', 100)->nullable();
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
        Schema::dropIfExists('template_master');
    }
}
