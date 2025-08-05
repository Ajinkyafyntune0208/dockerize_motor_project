<?php

use App\Models\ConfigSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDashboardTokenValidationSecretKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        ConfigSetting::updateOrCreate([
            'key' => 'constants.motorConstant.DASHBOARD_ENCRYPTION_TOKEN_SECRET_KEY'
        ], [
            'value' => 'As4563Hjkgtyk&6jhypolkcFRdf7',
            'label' => 'Secret Key of Dashboard for decrypting the token'
        ]);

        ConfigSetting::updateOrCreate([
            'key' => 'constants.motorConstant.MOTOR_ENCRYPTION_TOKEN_SECRET_KEY'
        ], [
            'value' => '39fDyPdoB3tHnOM690vOwbHrK3Y3Are',
            'label' => 'Secret Key of Motor for encrypting the token'
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
