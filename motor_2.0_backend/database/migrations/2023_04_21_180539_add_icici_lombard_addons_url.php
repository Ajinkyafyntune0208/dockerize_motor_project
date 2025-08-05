<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ConfigSettings;

class AddIciciLombardAddonsUrl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //

        if(env('APP_ENV') == 'local')
        {
            $ICICI_LOMBARD_ADDONS_COVER_URL = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_ADDONS_COVER_URL');

            if ($ICICI_LOMBARD_ADDONS_COVER_URL == null || empty($ICICI_LOMBARD_ADDONS_COVER_URL))
            {
                ConfigSettings::insert([
                    'label' => 'ICICI_LOMBARD_ADDONS_COVER_URL',
                    'key' => 'constants.IcConstants.icici_lombard.ICICI_LOMBARD_ADDONS_COVER_URL',
                    'value' => 'https://ilesbsanity.insurancearticlez.com/ilservices/motor/v1/master/AddOnCoverGrid',
                    'environment' => 'local'
                ]);
            }
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
