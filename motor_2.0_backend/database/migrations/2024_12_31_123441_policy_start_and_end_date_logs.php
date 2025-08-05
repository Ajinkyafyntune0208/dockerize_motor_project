<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PolicyStartAndEndDateLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_start_and_end_date_logs', function (Blueprint $table) {
            $table->id(); 
            $table->integer('enquiry_id');
            $table->integer('ic_id')->nullable();
            $table->string('ic_name')->nullable();
            $table->string('policy_start_date')->nullable();
            $table->string('policy_end_date')->nullable();
            $table->string('segment')->nullable();
            $table->integer('policy_id')->nullable();
            $table->string('proceed')->nullable();
            $table->string('status')->nullable();
            $table->string('comments')->nullable();
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
        Schema::dropIfExists('policy_start_and_end_date_logs');
    }
}
