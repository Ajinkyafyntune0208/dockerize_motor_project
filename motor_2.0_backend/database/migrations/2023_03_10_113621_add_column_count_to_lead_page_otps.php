<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCountToLeadPageOtps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('lead_page_otps', 'count')) {
            Schema::table('lead_page_otps', function (Blueprint $table) {
                $table->unsignedTinyInteger('count')->default(0);
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
        if (Schema::hasColumn('lead_page_otps', 'count')) {
            Schema::table('lead_page_otps', function (Blueprint $table) {
                $table->dropColumn('count');
            });
        }
    }
}
