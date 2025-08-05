<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->integer('role_id', true);
            $table->string('role_name', 50);
            $table->string('role_desc');
            $table->integer('type_id')->nullable();
            $table->integer('user_type_id')->nullable()->default(0);
            $table->string('status')->default('Active');
            $table->string('created_by', 100);
            $table->dateTime('created_on');
            $table->string('updated_by', 100)->nullable();
            $table->dateTime('updated_on')->nullable();
            $table->string('deleted_by', 100)->nullable();
            $table->dateTime('deleted_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_roles');
    }
}
