<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_user', function (Blueprint $table) {
            $table->integer('pos_id', true);
            $table->integer('user_id')->nullable();
            $table->string('is_employee', 5)->nullable();
            $table->string('user_name', 200)->nullable();
            $table->string('rm_name', 200)->nullable();
            $table->string('im_name', 200)->nullable();
            $table->string('agent_name', 500)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('ifsc_code', 50)->nullable();
            $table->string('bank_branch_name', 100)->nullable();
            $table->date('training_start_date')->nullable();
            $table->date('training_end_date')->nullable();
            $table->string('educational_qualification', 100)->nullable();
            $table->string('created_by', 5)->nullable();
            $table->dateTime('created_date')->useCurrent();
            $table->string('updated_by', 5)->nullable();
            $table->dateTime('updated_date')->useCurrent();
            $table->string('status', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_user');
    }
}
