<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Database\Seeders\DigitPrepaymentEnable;
use Illuminate\Database\Migrations\Migration;

class AddGodigitPrepaymentConfigurationForAllBrokers extends Migration
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
            $seeder = new DigitPrepaymentEnable();
            $seeder->run();
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
