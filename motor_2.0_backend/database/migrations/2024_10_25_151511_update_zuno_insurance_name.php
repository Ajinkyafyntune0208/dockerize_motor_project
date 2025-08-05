<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateZunoInsuranceName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $company_name = "Zuno General Insurance Limited";
        DB::table('master_company')
        ->where('company_id',43)
        ->update(
            ['company_name' => $company_name]
        );
        
        DB::table('previous_insurer_lists')
        ->where('name','Zuno General Insurance Co. Ltd.')
        ->update(
            ['name' => $company_name]
        );

        DB::table('previous_insurer_mappping')
        ->where('company_alias','edelweiss')
        ->update(
            ['previous_insurer' => $company_name]
        );

        DB::table('corporate_vehicles_quotes_request')
        ->where('previous_insurer_code','edelweiss')
        ->update(
            ['previous_insurer' => $company_name]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}
