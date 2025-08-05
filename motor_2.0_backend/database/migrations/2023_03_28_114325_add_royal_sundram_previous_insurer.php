<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddRoyalSundramPreviousInsurer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('previous_insurer_lists')->updateOrInsert([
            'name'=>'Royal Sundaram General Insurance Co. Ltd.', 'code' => 'Royal Sundaram General Insurance Co. Limited',  'company_alias' => 'royal_sundaram'
        ],
        [
            'company_alias' => 'royal_sundaram'
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
