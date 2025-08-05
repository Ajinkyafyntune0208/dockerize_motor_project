<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewSourceInRegistrationDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('registration_details', 'source')) {
            Schema::table('registration_details', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }


        Schema::table('registration_details', function (Blueprint $table) {
            $table->enum('source', ['Online', 'Offline'])->default('Online')->after('vehicle_details');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('registration_details', function (Blueprint $table) {
            //
        });
    }
}
