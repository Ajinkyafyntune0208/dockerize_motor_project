<?php

use App\Models\ConfigSetting;
use App\Models\ConfigSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConstantRemoveProxyForCkyc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! empty(config('constants.REMOVE_PROXY_FOR_CKYC'))) {
            ConfigSettings::insert([
                'label' => 'REMOVE_PROXY_FOR_CKYC',
                'key' => 'constants.REMOVE_PROXY_FOR_CKYC',
                'value' => 'Y',
                'environment' => env('APP_ENV')
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
