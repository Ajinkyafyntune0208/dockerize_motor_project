<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangePaymentEndDateDataTypeInCvBreakinStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cv_breakin_status', function (Blueprint $table) {
            $table->dateTime('payment_end_date')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cv_breakin_status', function (Blueprint $table) {
            $table->date('payment_end_date')->change();
        });
    }
}
