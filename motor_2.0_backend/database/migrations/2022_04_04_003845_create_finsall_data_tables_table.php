<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinsallDataTablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('finsall_data_table', function (Blueprint $table) {
            $table->id();
            $table->integer('enquiry_id')->nullable();
            $table->string('method_name')->nullable();
            $table->string('message')->nullable();
            $table->text('data')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('finsall_data_tables');
    }
}
