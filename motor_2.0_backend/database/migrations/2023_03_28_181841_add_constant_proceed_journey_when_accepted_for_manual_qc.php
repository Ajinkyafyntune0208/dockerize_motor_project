<?php

use App\Models\ConfigSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class AddConstantProceedJourneyWhenAcceptedForManualQc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        ConfigSetting::insert([
            'label' => 'PROCEED_JOURNEY_WHEN_ACCEPTED_FOR_MANUAL_QC',
            'key' => 'constants.IcConstants.icici_lombard.PROCEED_JOURNEY_WHEN_ACCEPTED_FOR_MANUAL_QC',
            'value' => 'Y',
            'environment' => env('APP_ENV')
        ]);

        Artisan::call('optimize:clear');
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
