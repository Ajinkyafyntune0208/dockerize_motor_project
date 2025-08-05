<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLoginTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_login', function (Blueprint $table) {
            $table->integer('ulm_id', true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address');
            $table->dateTime('timestamp');
            $table->string('status', 20);
			
			
			// $table->foreign('user_id', 'user_login_ibfk_1')->references('user_id')->on('users')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_login');
    }
}
