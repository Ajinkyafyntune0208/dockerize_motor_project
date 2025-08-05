<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->integer('ag_id', true);            
            $table->integer('agent_id');
            $table->text('agent_name')->nullable();
            $table->text('unique_number')->nullable();
            $table->text('user_name')->nullable();
            $table->text('father_name')->nullable();
            $table->text('gender')->nullable();
            $table->text('phone_no')->nullable();
            $table->text('mobile')->nullable();
            $table->text('email')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->text('marital_status')->nullable();
            $table->text('pan_no')->nullable();
            $table->text('aadhar_no')->nullable();
            $table->text('address')->nullable();
            $table->text('city')->nullable();
            $table->text('state')->nullable();
            $table->text('pincode')->nullable();
            $table->text('parent')->nullable();
            $table->text('level')->nullable();
            $table->text('usertype')->nullable();
            $table->text('supervisoer_name')->nullable();
            $table->text('supervisoer_emp_code')->nullable();
            $table->text('supervisoer_mobile')->nullable();
            $table->text('rm_branch')->nullable();
            $table->text('allowed_sections')->nullable();
            $table->text('comm_percent')->nullable();
            $table->text('status')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agents');
    }
}
