<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisableMasterPolicyDeletionConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        $DISABLE_MASTER_POLICY_DELETION = [
            ['label' => 'DISABLE_MASTER_POLICY_DELETION', 'key' => 'DISABLE_MASTER_POLICY_DELETION','value' => 'Y','environment' => 'test']
        ];

        \App\Models\ConfigSettings::insert($DISABLE_MASTER_POLICY_DELETION);
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
