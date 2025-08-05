<?php

use App\Models\ConfigSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateFinsallConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        ConfigSetting::where([
            'key' => 'constants.finsall.full_payment.UNIQUE_IDENTIFIER'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.full_payment.UNIQUE_IDENTIFIER'
        ]);


        ConfigSetting::where([
            'key' => 'constants.finsall.full_payment.USER_ID'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.full_payment.USER_ID'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.full_payment.CLIENT_ID'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.full_payment.CLIENT_ID'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.full_payment.CLIENT_KEY'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.full_payment.CLIENT_KEY'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.full_payment.AUTH_TOKEN'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.full_payment.AUTH_TOKEN'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.full_payment.AUTH_USERNAME'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.full_payment.AUTH_USERNAME'
        ]);

        //EMI
        ConfigSetting::where([
            'key' => 'constants.finsall.emi.UNIQUE_IDENTIFIER'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.emi.UNIQUE_IDENTIFIER'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.emi.USER_ID'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.emi.USER_ID'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.emi.CLIENT_ID'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.emi.CLIENT_ID'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.emi.CLIENT_KEY'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.emi.CLIENT_KEY'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.emi.AUTH_TOKEN'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.emi.AUTH_TOKEN'
        ]);

        ConfigSetting::where([
            'key' => 'constants.finsall.emi.AUTH_USERNAME'
        ])
        ->update([
            'key' => 'constants.finsall.reliance.emi.AUTH_USERNAME'
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
