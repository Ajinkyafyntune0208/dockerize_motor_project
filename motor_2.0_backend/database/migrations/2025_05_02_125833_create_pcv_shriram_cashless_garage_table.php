<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePcvShriramCashlessGarageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      if (!Schema::hasTable('pcv_shriram_cashless_garage'))  
      {
        Schema::create('pcv_shriram_cashless_garage', function (Blueprint $table) {
            $table->text("CodeNo")->nullable();
            $table->text("Name")->nullable();
            $table->text("CustEffFmDt")->nullable();
            $table->text("CustEffToDt")->nullable();
            $table->text("CustOffAddress")->nullable();
            $table->text("EmailId1")->nullable();
            $table->text("EmailId2")->nullable();
            $table->text("State")->nullable();
            $table->text("MobileNo")->nullable();
            $table->text("OffCity")->nullable();
            $table->text("PanNo")->nullable();
            $table->text("Accno")->nullable();
            $table->text("Bank")->nullable();
            $table->text("AcntType")->nullable();
            $table->text("Ifsccode")->nullable();
            $table->text("MicrCode")->nullable();
            $table->text("AcntHoldName")->nullable();
            $table->text("FaxGarage")->nullable();
            $table->text("DealingOff")->nullable();
            $table->text("TypeOfGarage")->nullable();
            $table->text("CashlessType")->nullable();
            $table->text("Specialization")->nullable();
            $table->text("TdsCode")->nullable();
        });
      }
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
