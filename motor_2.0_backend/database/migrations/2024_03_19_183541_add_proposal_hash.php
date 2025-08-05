<?php

use App\Models\ConfigSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProposalHash extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('config_settings')->updateOrInsert(
            ['key' => 'constants.PROPOSAL_HASH_ALLOWED_ICS'],
            ['label' => 'PROPOSAL_HASH_ALLOWED_ICS', 'value' => 'oriental']
        );

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
