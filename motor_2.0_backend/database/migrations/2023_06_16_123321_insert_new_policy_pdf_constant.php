<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InsertNewPolicyPdfConstant extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableName = 'config_settings';

        DB::table($tableName)->insert([[
                'label' => 'POLICY_DWLD_LINK_RELIANCE_NEW',
                'key' => 'constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW',
                'value' => 'https://rgipartners.reliancegeneral.co.in/api/service/DownloadScheduleLink',
                'environment' => 'local',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'POLICY_DWLD_LINK_RELIANCE_NEW_API_ENABLE',
                'key' => 'constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW_API_ENABLE',
                'value' => 'N',
                'environment' => 'local',
                'created_at' => now(),
                'updated_at' => now(),
            ]
            // Add more columns and values as needed
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
        $tableName = 'config_settings';

        // Delete the inserted row
        DB::table($tableName)->where('key', 'constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW')->delete();
        DB::table($tableName)->where('key', 'constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW_API_ENABLE')->delete();
    }
}
