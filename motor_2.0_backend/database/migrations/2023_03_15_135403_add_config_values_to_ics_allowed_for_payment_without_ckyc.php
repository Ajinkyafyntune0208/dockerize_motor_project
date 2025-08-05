<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddConfigValuesToIcsAllowedForPaymentWithoutCkyc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('config_settings')->updateOrInsert([
            'key'=>'ICS_ALLOWED_FOR_PAYMENT_WITHOUT_CKYC'
        ],
        [
            'label'=>'ICS_ALLOWED_FOR_PAYMENT_WITHOUT_CKYC',
            'value'=>'sbi',
            'environment'=>'local'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}
