<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFrontendTagsColumnInCorporateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'frontend_tags')) {
                $table->text('frontend_tags')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            if (Schema::hasColumn('corporate_vehicles_quotes_request', 'frontend_tags')) {
                $table->dropColumn('frontend_tags');
            }
        });
    }
}
