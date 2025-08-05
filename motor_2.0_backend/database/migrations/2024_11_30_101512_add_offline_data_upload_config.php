<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOfflineDataUploadConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $broker = config('constants.motorConstant.SMS_FOLDER');

        if (in_array($broker, [
            'spa',
            'renewbuy',
            'uib',
            'hero',
            'atelier',
            'tmibasl',
            'bajaj',
            'ace',
        ])) {
            DB::table('config_settings')->updateOrInsert([
                'key' => 'constants.motorConstant.OFFLINE_DATA_UPLOAD_ALLOWED'
            ], [
                'label' => 'OFFLINE_DATA_UPLOAD_ALLOWED',
                'value' => 'Y'
            ]);


            if (in_array($broker, [
                'spa',
                'renewbuy',
                'uib',
                'hero',
                'atelier',
                'tmibasl',
                'ace'
            ])) {
                DB::table('config_settings')->updateOrInsert([
                    'key' => 'constants.motorConstant.IS_FULL_DATA_UPLOAD'
                ], [
                    'label' => 'IS_FULL_DATA_UPLOAD',
                    'value' => 'Y'
                ]);
            } else {
                DB::table('config_settings')->updateOrInsert([
                    'key' => 'constants.motorConstant.IS_PARTIAL_DATA_UPLOAD'
                ], [
                    'label' => 'IS_PARTIAL_DATA_UPLOAD',
                    'value' => 'Y'
                ]);

                if (in_array($broker, [
                    'bajaj'
                ])) {
                    DB::table('config_settings')->updateOrInsert([
                        'key' => 'constants.motorConstant.IS_AGENT_TRANSFER_ALLOWED_IN_DATA_UPLOAD'
                    ], [
                        'label' => 'IS_AGENT_TRANSFER_ALLOWED_IN_DATA_UPLOAD',
                        'value' => 'Y'
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
}
