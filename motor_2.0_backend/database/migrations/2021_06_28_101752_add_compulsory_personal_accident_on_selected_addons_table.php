<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompulsoryPersonalAccidentOnSelectedAddonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('selected_addons', function (Blueprint $table) {
            $table->longText('compulsory_personal_accident')->nullable()->after('discounts');
            $table->longText('applicable_addons')->nullable()->after('addons');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('selected_addons', function (Blueprint $table) {
            $table->dropColumn('compulsory_personal_accident');
        });
    }
}
