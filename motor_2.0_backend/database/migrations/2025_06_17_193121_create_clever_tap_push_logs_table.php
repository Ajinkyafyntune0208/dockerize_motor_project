<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCleverTapPushLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('clever_tap_push_logs')) {
            Schema::create('clever_tap_push_logs', function (Blueprint $table) {
                $table->id();
                $table->string('trace_id')->nullable();
                $table->string('event_name')->nullable();
                $table->json('payload');
                $table->text('response')->nullable();
                $table->boolean('success')->default(false);
                $table->text('error_message')->nullable();
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
        Schema::dropIfExists('clever_tap_push_logs');
    }
}
