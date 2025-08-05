<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class RcNumberBlockDataNew extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Delete data based on a condition
        DB::table('rc_number_block_data')
        ->where('rc_number', 'NEW')
        ->delete();
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
