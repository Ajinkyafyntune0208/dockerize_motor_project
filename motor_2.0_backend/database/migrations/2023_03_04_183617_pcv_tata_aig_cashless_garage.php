<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PcvTataAigCashlessGarage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('pcv_tata_aig_cashless_garage')) {
            Schema::create('pcv_tata_aig_cashless_garage', function (Blueprint $table) {
                $table->text("CashLGar_Id")->nullable();
                $table->text("CashLGar_Cli_Id")->nullable();
                $table->text("CashLGar_Ic")->nullable();
                $table->text("CashLGar_VehicleTypeId")->nullable();
                $table->text("CashLGar_WSName")->nullable();
                $table->text("CashLGar_WSCode")->nullable();
                $table->text("CashLGar_WSCntNumber")->nullable();
                $table->text("CashLGar_WSAddress")->nullable();
                $table->text("CashLGar_WSPincode")->nullable();
                $table->text("CashLGar_WS_dlr_state_id")->nullable();
                $table->text("CashLGar_WS_dlr_city_id")->nullable();
                $table->text("CashLGar_WS_oem_id")->nullable();
                $table->text("CashLGar_WSRating")->nullable();
                $table->text("CashLGar_WSCashLess")->nullable();
                $table->text("CashLGar_WSSmartCashLess")->nullable();
                $table->text("CashLGar_InsUsrId")->nullable();
                $table->text("CashLGar_ModUsrId")->nullable();
                $table->text("CashLGar_InsDt")->nullable();
                $table->text("CashLGar_ModDt")->nullable();
                $table->text("CashLGar_InsIp")->nullable();
                $table->text("CashLGar_ModIp")->nullable();
                $table->text("CashLGar_Status")->nullable();
                
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
