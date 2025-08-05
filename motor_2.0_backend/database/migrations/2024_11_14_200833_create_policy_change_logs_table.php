<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyChangeLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasTable('policy_change_logs')) {
            Schema::create('policy_change_logs', function (Blueprint $table) {
                $table->id();
                $table->string('trace_id')->index();
                $table->string('user_id');
                $table->string('action_type');
                $table->string('policy_number');
                $table->string('screenshot_url')->nullable();
                $table->json('old_data')->nullable();
                $table->json('new_data')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policy_change_logs');
    }
}
