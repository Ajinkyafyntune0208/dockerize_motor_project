<?php

use App\Models\ConfigSetting;
use Illuminate\Database\Migrations\Migration;

class UpdateBajajProposalURL extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $old_url = config('constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE');
        ConfigSetting::insert([
            'label' => 'PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE',
            'key' => 'constants.motor.bajaj_allianz.PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE',
            'value' => $old_url ?? null,
            'environment' => app()->environment(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
