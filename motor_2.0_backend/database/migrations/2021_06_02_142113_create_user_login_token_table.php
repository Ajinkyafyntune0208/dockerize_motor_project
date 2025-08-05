<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLoginTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_login_token', function (Blueprint $table) {
            $table->integer('ult_id', true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token', 8000);
            $table->dateTime('created_at');
            $table->dateTime('token_expired');
            $table->string('email_link', 150);
            $table->string('status', 10)->nullable();
			
			
			// $table->foreign('user_id', 'user_login_token_ibfk_1')->references('user_id')->on('users')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_login_token');
    }
}
