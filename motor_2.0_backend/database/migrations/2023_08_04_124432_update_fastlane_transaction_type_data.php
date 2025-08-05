<?php

use App\Models\FastlaneRequestResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateFastlaneTransactionTypeData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        FastlaneRequestResponse::where('transaction_type', 'Fast Lance Service')
        ->update(['transaction_type' => 'Fast Lane Service']);
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
