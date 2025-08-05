<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
	/**
	* Run the migrations.
	*
	* @return void
	*/
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {

		$table->id();
		$table->string('user_id',50)->nullable();
		$table->string('first_name')->nullable();
		$table->string('last_name')->nullable();
		$table->string('password',100)->nullable();
		$table->string('email')->nullable();
		$table->char('gender',15)->nullable();
		$table->date('dob')->nullable();
		$table->string('mobile_no',15)->nullable();
		$table->string('address',1000)->nullable();
		$table->string('city',50)->nullable();
		$table->string('district',50)->nullable();
		$table->string('state',50)->nullable();
		$table->integer('pincode',)->nullable();
		$table->string('status',50)->nullable();
		$table->datetime('created_on')->useCurrent();
		$table->datetime('updated_on')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}