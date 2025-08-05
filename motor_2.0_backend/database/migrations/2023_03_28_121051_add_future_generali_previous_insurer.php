<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFutureGeneraliPreviousInsurer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('previous_insurer_lists')->updateOrInsert([
            'name'=>'Future Generali General Insurance', 'code' => '43207141',  'company_alias' => 'future_generali'
        ],
        [
            'company_alias' => 'future_generali'
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
