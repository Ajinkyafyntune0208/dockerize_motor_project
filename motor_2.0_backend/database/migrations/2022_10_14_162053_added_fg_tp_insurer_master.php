<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


class AddedFgTpInsurerMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('future_generali_previous_tp_insurer_master')) 
        {
            Schema::create('future_generali_previous_tp_insurer_master', function (Blueprint $table) {
                $table->string('client_code','10');
                $table->text('tp_insurer_name');
                $table->string('tp_insurer_code','10');
            });
            DB::table('future_generali_previous_tp_insurer_master')->insert([
                [
                    "client_code" => "43207040",
                    "tp_insurer_name" => "Bajaj Allianz General Insurance Co. Ltd.",
                    "tp_insurer_code" => "BJ",
                ],
                [
                    "client_code" => "43207086",
                    "tp_insurer_name" => "Bharti Axa General Insurance Co. Ltd.",
                    "tp_insurer_code" => "BA",
                ],
                [
                    "client_code" => "43207090",
                    "tp_insurer_name" => "Cholamandalam MS General Insurance Co. Ltd.",
                    "tp_insurer_code" => "CM",
                ], 
                [
                    "client_code" => "43207141",
                    "tp_insurer_name" => "Future Generali India Insurance Co. Ltd.",
                    "tp_insurer_code" => "FG",
                ], 
                [
                    "client_code" => "43207096",
                    "tp_insurer_name" => "HDFC ERGO General Insurance Co. Ltd.",
                    "tp_insurer_code" => "HD",
                ], 
                [
                    "client_code" => "43207099",
                    "tp_insurer_name" => "ICICI Lombard General Insurance Co. Ltd.",
                    "tp_insurer_code" => "IC",
                ], 
                [
                    "client_code" => "43207101",
                    "tp_insurer_name" => "IFFCO Tokio General Insurance Co. Ltd.",
                    "tp_insurer_code" => "IT",
                ], 
                [
                    "client_code" => "46349178",
                    "tp_insurer_name" => "Kotak Mahindra General Insurance Co. Ltd.",
                    "tp_insurer_code" => "KM",
                ], 
                [
                    "client_code" => "43207105",
                    "tp_insurer_name" => "L&T General Insurance Co. Ltd.",
                    "tp_insurer_code" => "LT",
                ], 
                [
                    "client_code" => "43207131",
                    "tp_insurer_name" => "Liberty Videocon General Insurance Co. Ltd.",
                    "tp_insurer_code" => "LV",
                ], 
                [
                    "client_code" => "43207146",
                    "tp_insurer_name" => "Magma HDI General Insurance Co. Ltd.",
                    "tp_insurer_code" => "MG",
                ], 
                [
                    "client_code" => "43207138",
                    "tp_insurer_name" => "National Insurance Co.ltd.",
                    "tp_insurer_code" => "NI",
                ], 
                [
                    "client_code" => "43207108",
                    "tp_insurer_name" => "Reliance General Insurance Co. Ltd.",
                    "tp_insurer_code" => "RG",
                ], 
                [
                    "client_code" => "43207112",
                    "tp_insurer_name" => "Royal Sundaram Alliance Insurance Co. Ltd",
                    "tp_insurer_code" => "RS",
                ], 
                [
                    "client_code" => "43207121",
                    "tp_insurer_name" => "Shriram General Insurance Co. Ltd.",
                    "tp_insurer_code" => "SG",
                ], 
                [
                    "client_code" => "43207137",
                    "tp_insurer_name" => "SBI General Insurance Co. Ltd.",
                    "tp_insurer_code" => "SB",
                ], 
                [
                    "client_code" => "43207122",
                    "tp_insurer_name" => "Tata AIG General Insurance Co. Ltd.",
                    "tp_insurer_code" => "TA",
                ], 
                [
                    "client_code" => "43207124",
                    "tp_insurer_name" => "The New India Assurance Co. Ltd.",
                    "tp_insurer_code" => "NE",
                ], 
                [
                    "client_code" => "43207126",
                    "tp_insurer_name" => "The Oriental Insurance Co. Ltd.",
                    "tp_insurer_code" => "OI",
                ], 
                [
                    "client_code" => "43207128",
                    "tp_insurer_name" => "United India Insurance Co. Ltd.",
                    "tp_insurer_code" => "UI",
                ], 
                [
                    "client_code" => "43207129",
                    "tp_insurer_name" => "Universal Sompo General Insurance Co. Ltd",
                    "tp_insurer_code" => "US",
                ], 
                [
                    "client_code" => "46349178",
                    "tp_insurer_name" => "Kotak Mahindra General Insurance Co. Ltd.",
                    "tp_insurer_code" => "KM",
                ], 
                [
                    "client_code" => "48525867",
                    "tp_insurer_name" => "Go Digit General Insurance Limited",
                    "tp_insurer_code" => "GD",
                ], 
                [
                    "client_code" => "49267663",
                    "tp_insurer_name" => "ACKO General Insurance",
                    "tp_insurer_code" => "AC",
                ], 
                [
                    "client_code" => "49277350",
                    "tp_insurer_name" => "EDELWEISS General Insurance",
                    "tp_insurer_code" => "EG",
                ], 
                [
                    "client_code" => "43207134",
                    "tp_insurer_name" => "Raheja QBE General Insurance Co. Ltd",
                    "tp_insurer_code" => "RQ",
                ],
                [
                    "client_code" => "50128044",
                    "tp_insurer_name" => "DHFL General Insurance Limited.",
                    "tp_insurer_code" => "DH",
                ]
        ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('future_generali_previous_tp_insurer_master');
    }
}
