<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTpColumnsToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
                $table->string('tp_start_date')->nullable()->after('policy_end_date');
                $table->string('tp_end_date')->nullable()->after('tp_start_date');
                $table->string('tp_insurance_company')->nullable()->after('tp_end_date');
                $table->string('tp_insurance_number')->nullable()->after('tp_insurance_company');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            $table->dropColumn('tp_start_date');
            $table->dropColumn('tp_end_date');
            $table->dropColumn('tp_insurance_company');
            $table->dropColumn('tp_insurance_number');
        });
    }
}
