<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterFastlaneRequestResponseAndRegistrationDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('fastlane_request_response', 'source')) {
            Schema::table('fastlane_request_response', function (Blueprint $table) {
                $table->enum('source', ['Online', 'Offline'])->default('Online');
            });
        }
        if (!Schema::hasColumn('registration_details', 'source')) {
            Schema::table('registration_details', function (Blueprint $table) {
                $table->enum('source', ['Online', 'Offline'])->default('Online');
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
        //
    }
}
