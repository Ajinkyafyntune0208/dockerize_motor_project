<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRegistrationDateUpdateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('registration_date_update_logs')) {
            Schema::create('registration_date_update_logs', function (Blueprint $table) {
                $table->id();
                $table->string('enquiry_id');
                $table->json('old_date');
                $table->json('new_date');
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
        Schema::dropIfExists('registration_date_update_logs');
    }
}
