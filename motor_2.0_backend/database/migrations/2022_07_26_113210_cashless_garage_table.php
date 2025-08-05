<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CashlessGarageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $company_name = [ 'edelweiss' , 'hdfc_ergo' , 'future_generali' ,'cholla_mandalam'];
        foreach ($company_name as $companies)
        {
            $table_name = $companies .'_car_cashless_garage';
            if(!Schema::hasTable($table_name))
            {
                Schema::create($table_name, function (Blueprint $table) {
                    $table->id();
                    $table->string('garage_name')->nullable();
                    $table->string('address')->nullable();
                    $table->string('pincode')->nullable();
                    $table->string('mobile')->nullable();
                });
            }
            
        }
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $company_name = [ 'edelweiss' , 'hdfc_ergo' , 'future_generali' ,'cholla_mandalam'];
        foreach ($company_name as $companies)
        {
            $table_name = $companies .'_car_cashless_garage';
            Schema::dropIfExists($table_name);
        }
    }
}
