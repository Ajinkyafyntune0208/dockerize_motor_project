<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOfflineMigrationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('offline_migration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->nullable()->index();
            $table->string('unique_key')->nullable()->index();
            $table->string('policy_number')->nullable()->index();
            $table->bigInteger('user_product_journey_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->string('status', 100)->default('Pending')->index();
            $table->dateTime('uploaded_at')->nullable();
            $table->json('extras')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('offline_migration_logs');
    }
}
