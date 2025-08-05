<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterPermissionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_permission', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('permission_name');
            $table->string('permission_desc');
            $table->dateTime('created_on')->useCurrent();
            $table->dateTime('updated_on');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_permission');
    }
}
