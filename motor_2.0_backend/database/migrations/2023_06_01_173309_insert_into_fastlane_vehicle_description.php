<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertIntoFastlaneVehicleDescription extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('fastlane_vehicle_description')) {
            DB::table('fastlane_vehicle_description')->insert([
                ['section' => 'bike', 'description' => '2W'],
                ['section' => 'bike', 'description' => '2WT'],
                ['section' => 'cv', 'description' => '3W'],
                ['section' => 'cv', 'description' => 'CV'],
                ['section' => 'cv', 'description' => 'E-RIC'],
                ['section' => 'cv', 'description' => 'MISC'],
                ['section' => 'cv', 'description' => 'PCV'],
                ['section' => 'cv', 'description' => 'TRACTOR']
            ]);
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
