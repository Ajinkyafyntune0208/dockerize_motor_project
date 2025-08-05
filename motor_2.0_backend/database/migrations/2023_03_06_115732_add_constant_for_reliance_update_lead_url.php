<?php

use App\Models\ConfigSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConstantForRelianceUpdateLeadUrl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $create_lead_url = config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_LEAD');

        ConfigSettings::insert([
            'label' => 'END_POINT_URL_RELIANCE_MOTOR_LEAD_UPDATE',
            'key' => 'constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_LEAD_UPDATE',
            'value' => $create_lead_url ?? null,
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
