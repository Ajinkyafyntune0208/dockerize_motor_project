<?php

use App\Models\ConfigSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBajajAllianzIssuePolicyUrl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $old_car_url = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_PROPOSAL_URL');
        $old_bike_url = config('constants.motor.bajaj_allianz.PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE');

        ConfigSetting::insert([[
            'label' => 'BAJAJ_ALLIANZ_CAR_POLICY_ISSUE_URL',
            'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_POLICY_ISSUE_URL',
            'value' => $old_car_url ?? null,
            'environment' => app()->environment(),
            'created_at' => now(),
            'updated_at' => now(),
        ], [
            'label' => 'END_POINT_URL_BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY',
            'key' => 'constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY',
            'value' => $old_bike_url ?? null,
            'environment' => app()->environment(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
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
