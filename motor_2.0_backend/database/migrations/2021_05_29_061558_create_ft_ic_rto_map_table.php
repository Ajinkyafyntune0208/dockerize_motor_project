<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFtIcRtoMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ft_ic_rto_map', function (Blueprint $table) {
            $table->string('ic_id', 10)->nullable();
            $table->integer('rto_id')->nullable();
            $table->string('rto_name', 10)->nullable();
            $table->string('rto_state', 10)->nullable();
            $table->string('status', 10)->nullable();
            $table->string('created_by', 10)->nullable();
            $table->dateTime('created_date')->nullable();
            $table->string('updated_by', 10)->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->string('deleted_by', 10)->nullable();
            $table->dateTime('deleted_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ft_ic_rto_map');
    }
}
