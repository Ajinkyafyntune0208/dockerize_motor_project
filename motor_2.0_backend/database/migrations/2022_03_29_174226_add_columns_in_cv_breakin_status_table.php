<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsInCvBreakinStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasColumn('cv_breakin_status', 'wimwisure_case_number'))
        {
            Schema::table('cv_breakin_status', function (Blueprint $table) {
                $table->string('wimwisure_case_number')->nullable();
            });
        }

        if ( ! Schema::hasColumn('cv_breakin_status', 'ic_breakin_response'))
        {
            Schema::table('cv_breakin_status', function (Blueprint $table) {
                $table->text('ic_breakin_response')->nullable();
            });
        }

        if ( ! Schema::hasColumn('cv_breakin_status', 'inspection_date'))
        {
            Schema::table('cv_breakin_status', function (Blueprint $table) {
                $table->dateTime('inspection_date')->nullable();
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
        Schema::table('cv_breakin_status', function (Blueprint $table) {
            $table->dropColumn('wimwisure_case_number');
            $table->dropColumn('ic_breakin_response');
            $table->dropColumn('inspection_date');
        });
    }
}
