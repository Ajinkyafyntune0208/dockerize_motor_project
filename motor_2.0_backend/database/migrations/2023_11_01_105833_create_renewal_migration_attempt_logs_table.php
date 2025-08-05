<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRenewalMigrationAttemptLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renewal_migration_attempt_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('renewal_data_migration_status_id');
            $table->bigInteger('attempt');
            $table->string('type');
            $table->string('status');
            $table->text('extras')->nullable();
            $table->timestamps();

            $table->index('renewal_data_migration_status_id', 'id_renewal_migration_attempt_logs');
            $table->index('type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('renewal_migration_attempt_logs');
    }
}
