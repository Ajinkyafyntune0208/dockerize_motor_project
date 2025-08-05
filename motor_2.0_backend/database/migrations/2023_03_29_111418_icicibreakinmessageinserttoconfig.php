<?php

use App\Models\ConfigSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Icicibreakinmessageinserttoconfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        $key = \App\Models\ConfigSetting::where('key','constants.IcConstants.icici_lombard.ICICI_LOMBARD_BREAKIN_FAIL_MESSAGE')
        ->first();
        if($key == null || empty($key))
        {
            \App\Models\ConfigSetting::insert([
                'label' => 'ICICI_LOMBARD_BREAKIN_FAIL_MESSAGE',
                'key' => 'constants.IcConstants.icici_lombard.ICICI_LOMBARD_BREAKIN_FAIL_MESSAGE',
                'value' => 'Given Reference No is Already Precessed For Vehicle Inspection.',
                'environment' => 'test',
                'created_at' => now(),
                'updated_at' => now()
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
        $key = \App\Models\ConfigSetting::where('key','constants.IcConstants.icici_lombard.ICICI_LOMBARD_BREAKIN_FAIL_MESSAGE')
        ->first();

        if($key != null || !empty($key))
        {
            \App\Models\ConfigSetting::where('key','constants.IcConstants.icici_lombard.ICICI_LOMBARD_BREAKIN_FAIL_MESSAGE')->delete();
        }
    }
}
