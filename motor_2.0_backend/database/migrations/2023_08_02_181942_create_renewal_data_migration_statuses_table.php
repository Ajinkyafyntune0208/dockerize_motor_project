<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRenewalDataMigrationStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renewal_data_migration_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 100)->nullable();
            $table->string('registration_number', 50)->nullable();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->longText('request')->nullable();
            $table->integer('attempts')->default(0);
            $table->enum('status', ['Pending', 'Success', 'Failed', 'Vahan Failed'])->default('Pending');
            $table->timestamps();
            $table->index('created_at');
            $table->index('policy_number');
            $table->index('registration_number');
            $table->index('user_product_journey_id');
            $table->index('attempts');
            $table->index('status');
            $table->index('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('renewal_data_migration_statuses');
    }
}
