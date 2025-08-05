<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTataAigFinanceMasterTable extends Migration
{
    public function up()
    {
        Schema::create('tata_aig_finance_master', function (Blueprint $table) {
            $table->string('num_financier_cd')->nullable();
            $table->string('txt_financier_name')->nullable();
            $table->string('txt_financier_short_name')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tata_aig_finance_master');
    }
}
