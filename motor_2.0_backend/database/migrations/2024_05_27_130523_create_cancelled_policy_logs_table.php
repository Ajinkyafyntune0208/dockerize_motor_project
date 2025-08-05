<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCancelledPolicyLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cancelled_policy_logs')) {
            Schema::create('cancelled_policy_logs', function (Blueprint $table) {
                $table->id();
                $table->integer('enquiry_id')->nullable();
                $table->string('request')->nullable();
                $table->string('response')->nullable();
                $table->string('source')->nullable();
                $table->string('reason')->nullable();
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
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
        Schema::dropIfExists('cancelled_policy_logs');
    }
}
